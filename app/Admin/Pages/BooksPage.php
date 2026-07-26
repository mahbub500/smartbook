<?php
/**
 * The SmartBook books list page.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Pages;

use SmartBook\Admin\Support\RedirectsWithNotice;
use SmartBook\Admin\Tables\BooksListTable;
use SmartBook\MetaBoxes\BookFields;
use SmartBook\PostTypes\BookPostType;
use SmartBook\Services\BarcodeManager;
use SmartBook\Taxonomies\GenreTaxonomy;
use SmartBook\Taxonomies\ShelfTaxonomy;

use function sb_option;

/**
 * Renders the custom books catalog (Admin\Tables\BooksListTable) and
 * processes the actions it can trigger: single/bulk trash, restore,
 * permanent delete, and a two-step bulk edit (an intermediate field
 * picker, then the actual update).
 *
 * Every mutating action is nonce-checked (the table's own "bulk-books"
 * nonce, reused for single-row action links too) and capability-checked
 * per post via current_user_can( 'edit_post' | 'delete_post', $id ),
 * which resolves through BookPostType's custom capability mapping.
 */
final class BooksPage {

	use RedirectsWithNotice;

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'sb_books';

	/**
	 * Nonce action for the intermediate bulk-edit form's own submission.
	 */
	private const BULK_EDIT_NONCE_ACTION = 'sb_bulk_edit_apply';

	/**
	 * @param BarcodeManager $barcodes Barcode storage/lifecycle manager, used by the "Search by barcode" scan box.
	 */
	public function __construct( private readonly BarcodeManager $barcodes ) {
	}

	/**
	 * Render the page: dispatches to the bulk-edit picker, processes a
	 * pending action, resolves a barcode scan, or renders the list table.
	 */
	public function render(): void {
		if ( ! current_user_can( BookPostType::CAP_EDIT_BOOKS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smartbook' ) );
		}

		if ( sb_option( 'enable_barcode', true ) ) {
			$this->maybe_handle_barcode_scan();
		}

		$ids = $this->requested_ids();

		if ( $this->is_bulk_edit_apply_request() ) {
			$this->handle_bulk_edit_apply( $ids );
		}

		$table  = new BooksListTable();
		$action = $table->current_action();

		if ( 'bulk_edit' === $action && array() !== $ids ) {
			check_admin_referer( 'bulk-books' );
			$this->render_bulk_edit_form( $ids );
			return;
		}

		if ( 'print_qr' === $action && array() !== $ids ) {
			check_admin_referer( 'bulk-books' );

			if ( ! sb_option( 'enable_qr', true ) ) {
				$this->redirect_with_notice( 'error', __( 'QR codes are disabled in SmartBook Settings.', 'smartbook' ) );
			}

			$this->redirect_to_print_labels( 'sb_qr_labels', $ids );
		}

		if ( 'print_barcode' === $action && array() !== $ids ) {
			check_admin_referer( 'bulk-books' );

			if ( ! sb_option( 'enable_barcode', true ) ) {
				$this->redirect_with_notice( 'error', __( 'Barcodes are disabled in SmartBook Settings.', 'smartbook' ) );
			}

			$this->redirect_to_print_labels( 'sb_barcode_labels', $ids );
		}

		if ( in_array( $action, array( 'trash', 'untrash', 'delete' ), true ) && array() !== $ids ) {
			$this->handle_row_action( $action, $ids );
		}

		$table->prepare_items();

		echo '<div class="wrap sb-admin-page">';
		printf( '<h1>%s ', esc_html__( 'Books', 'smartbook' ) );
		printf(
			'<a href="%s" class="page-title-action">%s</a></h1>',
			esc_url( admin_url( 'admin.php?page=sb_add_book' ) ),
			esc_html__( 'Add New', 'smartbook' )
		);

		$this->render_notice();

		if ( sb_option( 'enable_barcode', true ) ) {
			$this->render_scan_form();
		}

		echo '<form method="post">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( self::PAGE_SLUG ) );
		$table->views();
		$table->search_box( __( 'Search Books', 'smartbook' ), 'sb_book' );
		$table->display();
		echo '</form>';

		echo '</div>';
	}

	/**
	 * If a "sb_barcode_scan" value is present (typed, or entered by a
	 * USB barcode scanner acting as a keyboard), look it up and redirect
	 * straight to the matching book's edit screen — or back to this page
	 * with a "not found" notice. A plain text input submitting on Enter
	 * is exactly how barcode scanners are normally used, so no extra
	 * JavaScript is needed for the scan-to-open flow itself.
	 */
	private function maybe_handle_barcode_scan(): void {
		if ( ! isset( $_GET['sb_barcode_scan'] ) ) {
			return;
		}

		$value = sanitize_text_field( wp_unslash( $_GET['sb_barcode_scan'] ) );

		if ( '' === $value ) {
			return;
		}

		$post_id = $this->barcodes->find_post_by_barcode( $value );

		if ( null !== $post_id ) {
			wp_safe_redirect( (string) get_edit_post_link( $post_id, 'raw' ) );
			exit;
		}

		$this->redirect_with_notice(
			'error',
			sprintf(
				/* translators: %s: scanned barcode value. */
				__( 'No book found with barcode "%s".', 'smartbook' ),
				$value
			)
		);
	}

	/**
	 * Render the "scan or type a barcode" quick-search box.
	 */
	private function render_scan_form(): void {
		echo '<form method="get" class="sb-barcode-scan">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( self::PAGE_SLUG ) );
		printf(
			'<label for="sb-barcode-scan-input" class="screen-reader-text">%s</label>',
			esc_html__( 'Scan or type a barcode', 'smartbook' )
		);
		printf(
			'<input type="text" id="sb-barcode-scan-input" name="sb_barcode_scan" class="regular-text" placeholder="%s" autocomplete="off" />',
			esc_attr__( 'Scan or type a barcode…', 'smartbook' )
		);
		submit_button( __( 'Find Book', 'smartbook' ), '', '', false );
		echo '</form>';
	}

	/**
	 * Process a trash/untrash/delete action (single row or bulk) and
	 * redirect back with a result notice.
	 *
	 * @param string $action Requested action: "trash", "untrash", or "delete".
	 * @param int[]  $ids    Post IDs to act on.
	 */
	private function handle_row_action( string $action, array $ids ): void {
		check_admin_referer( 'bulk-books' );

		$count = 0;

		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'delete_post', $id ) ) {
				continue;
			}

			$success = match ( $action ) {
				'trash'   => (bool) wp_trash_post( $id ),
				'untrash' => (bool) wp_untrash_post( $id ),
				'delete'  => (bool) wp_delete_post( $id, true ),
				default   => false,
			};

			if ( $success ) {
				++$count;
			}
		}

		$this->redirect_with_notice( 'success', $this->row_action_message( $action, $count ) );
	}

	/**
	 * Redirect the selected books straight to a label print sheet. This
	 * is a navigation, not a mutation, so unlike handle_row_action() it
	 * doesn't touch any data or show a result notice.
	 *
	 * @param string $page_slug Either "sb_qr_labels" or "sb_barcode_labels".
	 * @param int[]  $ids       Post IDs to print labels for.
	 */
	private function redirect_to_print_labels( string $page_slug, array $ids ): never {
		$args = array(
			'page'            => $page_slug,
			'sb_print_labels' => '1',
			'sb_book_id'      => $ids,
		);

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );

		exit;
	}

	/**
	 * Translated, pluralized confirmation message for a processed action.
	 */
	private function row_action_message( string $action, int $count ): string {
		return match ( $action ) {
			/* translators: %d: number of books moved to Trash. */
			'trash'   => sprintf( _n( '%d book moved to Trash.', '%d books moved to Trash.', $count, 'smartbook' ), $count ),
			/* translators: %d: number of books restored. */
			'untrash' => sprintf( _n( '%d book restored.', '%d books restored.', $count, 'smartbook' ), $count ),
			/* translators: %d: number of books permanently deleted. */
			'delete'  => sprintf( _n( '%d book permanently deleted.', '%d books permanently deleted.', $count, 'smartbook' ), $count ),
			default   => '',
		};
	}

	/**
	 * Whether the intermediate bulk-edit form itself is being submitted.
	 */
	private function is_bulk_edit_apply_request(): bool {
		return isset( $_POST['sb_bulk_edit_apply'] ) && '1' === $_POST['sb_bulk_edit_apply'];
	}

	/**
	 * Render the "pick which fields to change" intermediate step for
	 * bulk editing. Every field defaults to "No Change" so an admin can
	 * update just one attribute across many books at once.
	 *
	 * @param int[] $ids Post IDs selected in the list table.
	 */
	private function render_bulk_edit_form( array $ids ): void {
		echo '<div class="wrap sb-admin-page">';
		printf(
			'<h1>%s</h1>',
			esc_html(
				sprintf(
					/* translators: %d: number of selected books. */
					__( 'Bulk Edit %d Book(s)', 'smartbook' ),
					count( $ids )
				)
			)
		);

		echo '<form method="post">';
		wp_nonce_field( self::BULK_EDIT_NONCE_ACTION );
		echo '<input type="hidden" name="sb_bulk_edit_apply" value="1" />';

		foreach ( $ids as $id ) {
			printf( '<input type="hidden" name="sb_book_id[]" value="%d" />', $id );
		}

		$this->render_bulk_select( 'sb_bulk_genre', __( 'Genre', 'smartbook' ), $this->term_options( GenreTaxonomy::SLUG ) );
		$this->render_bulk_select( 'sb_bulk_shelf', __( 'Shelf', 'smartbook' ), $this->term_options( ShelfTaxonomy::SLUG ) );
		$this->render_bulk_select( 'sb_bulk_status', __( 'Reading Status', 'smartbook' ), BooksListTable::status_labels() );
		$this->render_bulk_select(
			'sb_bulk_favorite',
			__( 'Favorite', 'smartbook' ),
			array(
				'1' => __( 'Mark as Favorite', 'smartbook' ),
				'0' => __( 'Remove from Favorites', 'smartbook' ),
			)
		);

		submit_button( __( 'Apply Changes', 'smartbook' ) );

		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Cancel', 'smartbook' )
		);

		echo '</form></div>';
	}

	/**
	 * Render one "No Change" + options <select> for the bulk-edit form.
	 *
	 * @param array<string, string> $options Value => label pairs.
	 */
	private function render_bulk_select( string $name, string $label, array $options ): void {
		printf( '<div class="sb-field-group"><label for="%1$s">%2$s</label>', esc_attr( $name ), esc_html( $label ) );
		printf( '<select id="%1$s" name="%1$s">', esc_attr( $name ) );
		printf( '<option value="">%s</option>', esc_html__( '— No Change —', 'smartbook' ) );

		foreach ( $options as $value => $option_label ) {
			printf( '<option value="%1$s">%2$s</option>', esc_attr( (string) $value ), esc_html( $option_label ) );
		}

		echo '</select></div>';
	}

	/**
	 * Apply the submitted bulk-edit form to every selected post.
	 *
	 * @param int[] $ids Post IDs to update.
	 */
	private function handle_bulk_edit_apply( array $ids ): void {
		check_admin_referer( self::BULK_EDIT_NONCE_ACTION );

		if ( array() === $ids ) {
			$this->redirect_with_notice( 'error', __( 'No books were selected.', 'smartbook' ) );
		}

		$genre    = isset( $_POST['sb_bulk_genre'] ) ? sanitize_key( wp_unslash( $_POST['sb_bulk_genre'] ) ) : '';
		$shelf    = isset( $_POST['sb_bulk_shelf'] ) ? sanitize_key( wp_unslash( $_POST['sb_bulk_shelf'] ) ) : '';
		$status   = isset( $_POST['sb_bulk_status'] ) ? sanitize_key( wp_unslash( $_POST['sb_bulk_status'] ) ) : '';
		$favorite = isset( $_POST['sb_bulk_favorite'] ) ? sanitize_key( wp_unslash( $_POST['sb_bulk_favorite'] ) ) : '';

		$updated = 0;

		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}

			if ( '' !== $genre ) {
				wp_set_object_terms( $id, array( $genre ), GenreTaxonomy::SLUG );
			}

			if ( '' !== $shelf ) {
				wp_set_object_terms( $id, array( $shelf ), ShelfTaxonomy::SLUG );
			}

			if ( '' !== $status ) {
				update_post_meta( $id, 'sb_status', BookFields::sanitize( 'sb_status', $status ) );
			}

			if ( '' !== $favorite ) {
				update_post_meta( $id, 'sb_favorite', BookFields::sanitize( 'sb_favorite', '1' === $favorite ) );
			}

			++$updated;
		}

		$this->redirect_with_notice(
			'success',
			sprintf(
				/* translators: %d: number of books updated. */
				__( '%d book(s) updated.', 'smartbook' ),
				$updated
			)
		);
	}

	/**
	 * Term slug => name options for a taxonomy <select>.
	 *
	 * @return array<string, string>
	 */
	private function term_options( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$options = array();

		foreach ( $terms as $term ) {
			$options[ $term->slug ] = $term->name;
		}

		return $options;
	}

	/**
	 * Post IDs submitted via the "sb_book_id[]" checkboxes or a single
	 * row-action link.
	 *
	 * @return int[]
	 */
	private function requested_ids(): array {
		if ( ! isset( $_REQUEST['sb_book_id'] ) ) {
			return array();
		}

		$raw = wp_unslash( $_REQUEST['sb_book_id'] );
		$raw = is_array( $raw ) ? $raw : array( $raw );

		return array_values( array_filter( array_map( 'absint', $raw ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	private function notice_page_slug(): string {
		return self::PAGE_SLUG;
	}
}
