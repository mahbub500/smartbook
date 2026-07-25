<?php
/**
 * The SmartBook import/export page.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Pages;

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
	 * admin-post.php action name for exporting.
	 */
	private const EXPORT_ACTION = 'sb_export_books';

	/**
	 * admin-post.php action name for importing.
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
			array(
				'post_type'      => BookPostType::SLUG,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
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

		fclose( $handle );
		exit;
	}

	/**
	 * Parse an uploaded CSV file and create/update books from it.
	 */
	public function handle_import(): void {
		$this->verify_request();

		if ( ! isset( $_FILES[ self::FILE_FIELD ] ) || UPLOAD_ERR_OK !== $_FILES[ self::FILE_FIELD ]['error'] ) {
			$this->redirect_with_notice( 'error', __( 'Please choose a CSV file to upload.', 'smartbook' ) );
		}

		$file     = $_FILES[ self::FILE_FIELD ];
		$filetype = wp_check_filetype( $file['name'], array( 'csv' => 'text/csv' ) );

		if ( 'csv' !== $filetype['ext'] ) {
			$this->redirect_with_notice( 'error', __( 'Only CSV files are supported.', 'smartbook' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$handle = fopen( $file['tmp_name'], 'r' );

		if ( false === $handle ) {
			$this->redirect_with_notice( 'error', __( 'The uploaded file could not be read.', 'smartbook' ) );
		}

		$header = fgetcsv( $handle );

		if ( false === $header ) {
			fclose( $handle );
			$this->redirect_with_notice( 'error', __( 'The uploaded file is empty.', 'smartbook' ) );
		}

		$imported = 0;

		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			$data = array_combine( $header, array_pad( $row, count( $header ), '' ) );

			if ( false === $data || empty( $data['title'] ) ) {
				continue;
			}

			$this->import_row( $data );
			++$imported;
		}

		fclose( $handle );

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

		if ( $post_id > 0 && get_post( $post_id ) instanceof WP_Post ) {
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
