<?php
/**
 * The SmartBook import/export page.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Admin\Support\RedirectsWithNotice;
use SmartBook\Core\Contracts\Hookable;
use SmartBook\MetaBoxes\BookFields;
use SmartBook\PostTypes\BookPostType;
use WP_Post;

/**
 * Lets users download every book as a CSV file, and re-import a
 * previously exported (or compatibly formatted) CSV file to create or
 * update books.
 *
 * Both directions run behind a nonce check and a capability check
 * (verify_request()); import values are sanitized per-field via
 * BookFields::sanitize(); exported cell values are neutralized against
 * spreadsheet formula injection via csv_safe().
 */
final class ImportExportPage implements Hookable {

	use RedirectsWithNotice;

	/**
	 * Admin-post.php action name for exporting.
	 */
	private const EXPORT_ACTION = 'sb_export_books';

	/**
	 * Admin-post.php action name for importing.
	 */
	private const IMPORT_ACTION = 'sb_import_books';

	/**
	 * Shared nonce action for both directions.
	 */
	private const NONCE_ACTION = 'sb_import_export_nonce';

	/**
	 * Nonce hidden field name.
	 */
	private const NONCE_NAME = 'sb_import_export_nonce_field';

	/**
	 * Name of the file upload field.
	 */
	private const FILE_FIELD = 'sb_import_file';

	/**
	 * Admin page slug, used to redirect back with a result notice.
	 */
	private const PAGE_SLUG = 'sb_import_export';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( BookPostType::CAP_EDIT_BOOKS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smartbook' ) );
		}

		echo '<div class="wrap sb-admin-page">';
		printf( '<h1>%s</h1>', esc_html__( 'Import / Export', 'smartbook' ) );

		$this->render_notice();

		printf( '<h2>%s</h2>', esc_html__( 'Export', 'smartbook' ) );
		printf( '<p>%s</p>', esc_html__( 'Download every book and its details as a CSV file.', 'smartbook' ) );
		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::EXPORT_ACTION ) );
		submit_button( __( 'Export to CSV', 'smartbook' ) );
		echo '</form>';

		printf( '<h2>%s</h2>', esc_html__( 'Import', 'smartbook' ) );
		printf( '<p>%s</p>', esc_html__( 'Upload a CSV file exported from SmartBook to create or update books.', 'smartbook' ) );
		printf( '<form method="post" action="%s" enctype="multipart/form-data">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::IMPORT_ACTION ) );
		printf( '<input type="file" name="%s" accept=".csv" required="required" />', esc_attr( self::FILE_FIELD ) );
		submit_button( __( 'Import from CSV', 'smartbook' ) );
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Stream every book as a CSV download.
	 */
	public function handle_export(): void {
		$this->verify_request();

		$posts = get_posts(
			array_merge(
				array(
					'post_type'      => BookPostType::SLUG,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				),
				BookPostType::author_scope_args()
			)
		);

		$meta_keys = array_keys( BookFields::definitions() );
		$columns   = array_merge( array( 'ID', 'title', 'status' ), $meta_keys );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="smartbook-export-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$handle = fopen( 'php://output', 'w' );

		fputcsv( $handle, $columns );

		foreach ( $posts as $post ) {
			$row = array( (string) $post->ID, $this->csv_safe( $post->post_title ), $post->post_status );

			foreach ( $meta_keys as $key ) {
				$row[] = $this->csv_safe( (string) get_post_meta( $post->ID, $key, true ) );
			}

			fputcsv( $handle, $row );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes the "php://output" stream opened above, not a file on disk.
		exit;
	}

	/**
	 * Parse an uploaded CSV file and create/update books from it.
	 */
	public function handle_import(): void {
		$this->verify_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- nonce already verified above; UPLOAD_ERR_OK check below is exactly the existence/validity check the sniff is asking for.
		if ( ! isset( $_FILES[ self::FILE_FIELD ] ) || UPLOAD_ERR_OK !== $_FILES[ self::FILE_FIELD ]['error'] ) {
			$this->redirect_with_notice( 'error', __( 'Please choose a CSV file to upload.', 'smartbook' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce already verified above; $_FILES is a server-populated superglobal, not free-form user text, and every value used below is separately validated (filetype) or read straight from disk (tmp_name).
		$file     = $_FILES[ self::FILE_FIELD ];
		$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'csv' => 'text/csv' ) );

		if ( 'csv' !== $filetype['ext'] ) {
			$this->redirect_with_notice( 'error', __( 'Only CSV files are supported.', 'smartbook' ) );
		}

		$contents = $this->read_uploaded_file( $file['tmp_name'] );

		if ( false === $contents ) {
			$this->redirect_with_notice( 'error', __( 'The uploaded file could not be read.', 'smartbook' ) );
		}

		// An in-memory stream (not a real filesystem path) so fgetcsv() can
		// do RFC4180-correct parsing of quoted multi-line cells (e.g. a
		// multi-line "sb_notes" value), which naive line-splitting would break.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- "php://temp/" is an in-memory stream, not a file on disk.
		$handle = fopen( 'php://temp/', 'r+' );
		fwrite( $handle, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- writing into the in-memory stream opened above, not a file on disk.
		rewind( $handle );

		$header = fgetcsv( $handle );

		if ( false === $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes the in-memory stream opened above, not a file on disk.
			$this->redirect_with_notice( 'error', __( 'The uploaded file is empty.', 'smartbook' ) );
		}

		$imported = 0;

		// Assignment-in-condition is the standard idiom for draining an
		// fgetcsv() stream; false is its own defined "no more rows" signal.
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			$data = array_combine( $header, array_pad( $row, count( $header ), '' ) );

			if ( false === $data || empty( $data['title'] ) ) {
				continue;
			}

			$this->import_row( $data );
			++$imported;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes the in-memory stream opened above, not a file on disk.

		$this->redirect_with_notice(
			'success',
			sprintf(
				/* translators: %d: number of books imported. */
				__( 'Imported %d book(s).', 'smartbook' ),
				$imported
			)
		);
	}

	/**
	 * Read an uploaded file's contents via WP_Filesystem rather than a
	 * direct filesystem call, per WordPress's file-operations guidelines.
	 *
	 * @param string $tmp_name PHP-managed temporary upload path from $_FILES.
	 *
	 * @return string|false File contents, or false on failure.
	 */
	private function read_uploaded_file( string $tmp_name ): string|false {
		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem->get_contents( $tmp_name );
	}

	/**
	 * Create or update a single book from one parsed CSV row.
	 *
	 * @param array<string, string> $data Column name => raw cell value.
	 */
	private function import_row( array $data ): void {
		$post_id = isset( $data['ID'] ) ? absint( $data['ID'] ) : 0;

		$post_data = array(
			'post_type'   => BookPostType::SLUG,
			'post_title'  => sanitize_text_field( $data['title'] ),
			'post_status' => in_array( $data['status'] ?? '', array( 'publish', 'draft', 'pending', 'private' ), true )
				? $data['status']
				: 'draft',
		);

		if ( $post_id > 0 ) {
			$existing = get_post( $post_id );

			// The row targets an existing post: only allow the update if it is
			// already a book and the current user is allowed to edit that
			// specific book (not just books in general), otherwise a crafted
			// CSV row could hijack any post ID on the site.
			if ( ! $existing instanceof WP_Post
				|| BookPostType::SLUG !== $existing->post_type
				|| ! current_user_can( 'edit_post', $post_id )
			) {
				return;
			}

			$post_data['ID'] = $post_id;
			wp_update_post( $post_data );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) || 0 === $post_id ) {
			return;
		}

		foreach ( array_keys( BookFields::definitions() ) as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			update_post_meta( $post_id, $key, BookFields::sanitize( $key, $data[ $key ] ) );
		}
	}

	/**
	 * Verify the nonce and capability for both the export and import
	 * actions, dying on failure exactly like a core admin-post handler.
	 */
	private function verify_request(): void {
		if ( ! current_user_can( BookPostType::CAP_EDIT_BOOKS ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'smartbook' ) );
		}

		$nonce = isset( $_REQUEST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'smartbook' ) );
		}
	}

	/**
	 * Prefix a cell with an apostrophe if it starts with a character a
	 * spreadsheet application would interpret as the start of a formula,
	 * preventing CSV formula injection when the file is opened in Excel
	 * or similar.
	 *
	 * @param string $value Cell value.
	 */
	private function csv_safe( string $value ): string {
		if ( '' !== $value && str_contains( '=+-@', $value[0] ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	private function notice_page_slug(): string {
		return self::PAGE_SLUG;
	}
}
