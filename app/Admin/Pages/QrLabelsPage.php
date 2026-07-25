<?php
/**
 * The SmartBook QR labels page.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Pages;

use SmartBook\PostTypes\BookPostType;
use SmartBook\Services\QrCodeManager;
use SmartBook\Taxonomies\ShelfTaxonomy;
use WP_Post;

/**
 * A two-step, GET-only (read-only, so no nonce is needed) flow: pick
 * which books to print labels for, then a print-optimized A4 sheet of
 * QR + title + shelf labels for the chosen books. Reachable from the
 * books table's "Print QR Labels" bulk action, its per-row "Print
 * Label" action, and the QR Code meta box's "Print Label" link — all
 * three simply link here with the relevant "sb_book_id[]" values.
 */
final class QrLabelsPage {

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'sb_qr_labels';

	/**
	 * @param QrCodeManager $qr_codes QR code storage/lifecycle manager.
	 */
	public function __construct( private readonly QrCodeManager $qr_codes ) {
	}

	/**
	 * Render the page: the print sheet when "sb_print_labels=1" is
	 * present, otherwise the book selection checklist.
	 */
	public function render(): void {
		if ( ! current_user_can( BookPostType::CAP_EDIT_BOOKS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smartbook' ) );
		}

		if ( isset( $_GET['sb_print_labels'] ) && '1' === $_GET['sb_print_labels'] ) {
			$this->render_print_sheet( $this->requested_ids() );

			return;
		}

		$this->render_selection_form();
	}

	/**
	 * Render the "which books?" checklist.
	 */
	private function render_selection_form(): void {
		$preselected = $this->requested_ids();
		$books       = $this->all_books();

		echo '<div class="wrap sb-admin-page">';
		printf( '<h1>%s</h1>', esc_html__( 'QR Labels', 'smartbook' ) );
		printf( '<p>%s</p>', esc_html__( 'Choose which books to print QR labels for.', 'smartbook' ) );

		if ( array() === $books ) {
			printf( '<p>%s</p>', esc_html__( 'No books found.', 'smartbook' ) );
			echo '</div>';

			return;
		}

		printf( '<form method="get" action="%s">', esc_url( admin_url( 'admin.php' ) ) );
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( self::PAGE_SLUG ) );
		echo '<input type="hidden" name="sb_print_labels" value="1" />';

		printf(
			'<p><label><input type="checkbox" id="sb-select-all-books" /> %s</label></p>',
			esc_html__( 'Select All', 'smartbook' )
		);

		echo '<ul class="sb-qr-select-list">';

		foreach ( $books as $book ) {
			printf(
				'<li><label><input type="checkbox" name="sb_book_id[]" value="%1$d" class="sb-qr-select-list__checkbox" %2$s /> %3$s</label></li>',
				$book->ID,
				checked( array() === $preselected || in_array( $book->ID, $preselected, true ), true, false ),
				esc_html( get_the_title( $book ) )
			);
		}

		echo '</ul>';

		submit_button( __( 'Print Labels', 'smartbook' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the print-optimized A4 label sheet.
	 *
	 * @param int[] $ids Book IDs to print; empty means every book.
	 */
	private function render_print_sheet( array $ids ): void {
		$books = array() === $ids ? $this->all_books() : $this->books_by_ids( $ids );

		foreach ( $books as $book ) {
			$this->qr_codes->ensure_generated( $book->ID );
		}

		echo '<div class="wrap sb-admin-page sb-qr-print-page">';

		echo '<div class="sb-no-print">';
		printf( '<h1>%s</h1>', esc_html__( 'QR Labels — Print Preview', 'smartbook' ) );
		printf(
			'<p><button type="button" class="button button-primary" data-sb-print="1">%1$s</button> <a class="button" href="%2$s">%3$s</a></p>',
			esc_html__( 'Print', 'smartbook' ),
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Back to Selection', 'smartbook' )
		);
		echo '</div>';

		if ( array() === $books ) {
			printf( '<p class="sb-no-print">%s</p>', esc_html__( 'No books selected.', 'smartbook' ) );
			echo '</div>';

			return;
		}

		echo '<div class="sb-qr-labels-grid">';

		foreach ( $books as $book ) {
			$this->render_label( $book );
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render a single label: QR code, title, and shelf.
	 */
	private function render_label( WP_Post $book ): void {
		$qr_url = $this->qr_codes->url_for( $book->ID );
		$shelf  = $this->shelf_names( $book->ID );

		echo '<div class="sb-qr-label">';

		if ( '' !== $qr_url ) {
			printf( '<img src="%s" alt="" class="sb-qr-label__image" />', esc_url( $qr_url ) );
		}

		printf( '<span class="sb-qr-label__title">%s</span>', esc_html( get_the_title( $book ) ) );

		if ( '' !== $shelf ) {
			printf( '<span class="sb-qr-label__shelf">%s</span>', esc_html( $shelf ) );
		}

		echo '</div>';
	}

	/**
	 * Comma-separated shelf term names for a book.
	 */
	private function shelf_names( int $post_id ): string {
		$terms = get_the_terms( $post_id, ShelfTaxonomy::SLUG );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}

	/**
	 * Book IDs requested via "sb_book_id[]".
	 *
	 * @return int[]
	 */
	private function requested_ids(): array {
		if ( ! isset( $_GET['sb_book_id'] ) ) {
			return array();
		}

		$raw = wp_unslash( $_GET['sb_book_id'] );
		$raw = is_array( $raw ) ? $raw : array( $raw );

		return array_values( array_filter( array_map( 'absint', $raw ) ) );
	}

	/**
	 * Every non-trash book, alphabetical by title.
	 *
	 * @return WP_Post[]
	 */
	private function all_books(): array {
		return get_posts(
			array(
				'post_type'      => BookPostType::SLUG,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Specific books by ID, in the given order.
	 *
	 * @param int[] $ids Post IDs.
	 *
	 * @return WP_Post[]
	 */
	private function books_by_ids( array $ids ): array {
		return get_posts(
			array(
				'post_type'      => BookPostType::SLUG,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'post__in'       => $ids,
				'orderby'        => 'post__in',
			)
		);
	}
}
