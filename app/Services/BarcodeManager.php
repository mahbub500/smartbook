<?php
/**
 * Barcode value assignment, storage, and lookup.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Picqer\Barcode\Exceptions\BarcodeException;
use SmartBook\PostTypes\BookPostType;
use WP_Post;
use WP_Query;

/**
 * Assigns each book a unique Code128-compatible barcode value (stored
 * as the "sb_barcode" post meta, editable via BookDetailsMetaBox),
 * renders it as an SVG image under uploads/sb-barcodes/{post_id}.svg,
 * and looks books up by scanned barcode value.
 */
final class BarcodeManager {

	/**
	 * Post meta key holding the human-readable barcode value. Also the
	 * BookFields field key, so BookDetailsMetaBox/CSV import-export
	 * already read and sanitize it like any other text field.
	 */
	public const META_VALUE = 'sb_barcode';

	/**
	 * Post meta key holding the public URL of the generated barcode image.
	 */
	private const META_IMAGE_URL = 'sb_barcode_image_url';

	/**
	 * Uploads subdirectory barcode images are stored in.
	 */
	private const DIRECTORY_NAME = 'sb-barcodes';

	/**
	 * Create the barcode manager.
	 *
	 * @param BarcodeGenerator $generator SVG Code128 renderer.
	 * @param LoggerInterface  $logger    Logger, used when generation fails.
	 */
	public function __construct(
		private readonly BarcodeGenerator $generator,
		private readonly LoggerInterface $logger
	) {
	}

	/**
	 * A book's current barcode value, or '' if none has been assigned yet.
	 *
	 * @param int $post_id Book post ID.
	 */
	public function value_for( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_VALUE, true );
	}

	/**
	 * Public URL of a book's barcode image, or '' if none has been
	 * generated yet.
	 *
	 * @param int $post_id Book post ID.
	 */
	public function url_for( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_IMAGE_URL, true );
	}

	/**
	 * Whether a book already has a generated barcode image.
	 *
	 * @param int $post_id Book post ID.
	 */
	public function has_barcode( int $post_id ): bool {
		return '' !== $this->url_for( $post_id );
	}

	/**
	 * Ensure a book has both a barcode value and a generated image,
	 * assigning/rendering only what's missing.
	 *
	 * @param int $post_id Book post ID.
	 */
	public function ensure_generated( int $post_id ): void {
		$value = $this->ensure_value( $post_id );

		if ( '' === $this->url_for( $post_id ) ) {
			$this->render_image( $post_id, $value );
		}
	}

	/**
	 * Re-render a book's barcode image from its current value,
	 * overwriting any previous image. Assigns a value first if the book
	 * doesn't have one yet.
	 *
	 * @param int $post_id Book post ID.
	 */
	public function regenerate( int $post_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || BookPostType::SLUG !== $post->post_type ) {
			return false;
		}

		return $this->render_image( $post_id, $this->ensure_value( $post_id ) );
	}

	/**
	 * Find the post a scanned barcode value belongs to.
	 *
	 * @param string $value Scanned barcode value.
	 */
	public function find_post_by_barcode( string $value ): ?int {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'      => BookPostType::SLUG,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_VALUE,
						'value' => $value,
					),
				),
			)
		);

		return array() !== $query->posts ? (int) $query->posts[0] : null;
	}

	/**
	 * Remove a book's stored barcode image file and its meta, e.g. when
	 * the book is permanently deleted.
	 *
	 * @param int $post_id Book post ID.
	 */
	public function delete_for_post( int $post_id ): void {
		$file_path = trailingslashit( $this->directory() ) . $post_id . '.svg';

		if ( file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}

		delete_post_meta( $post_id, self::META_IMAGE_URL );
	}

	/**
	 * Absolute filesystem path to the barcode storage directory. Public
	 * so Core\Uninstaller can remove it entirely on uninstall.
	 */
	public function directory(): string {
		return trailingslashit( wp_upload_dir()['basedir'] ) . self::DIRECTORY_NAME;
	}

	/**
	 * Return the book's barcode value, assigning a unique one
	 * ("SB" + the zero-padded post ID, which is inherently unique) if
	 * it doesn't have one yet.
	 *
	 * @param int $post_id Book post ID.
	 */
	private function ensure_value( int $post_id ): string {
		$value = $this->value_for( $post_id );

		if ( '' !== $value ) {
			return $value;
		}

		$value = 'SB' . str_pad( (string) $post_id, 8, '0', STR_PAD_LEFT );

		update_post_meta( $post_id, self::META_VALUE, $value );

		return $value;
	}

	/**
	 * Render and save the barcode image for a given value.
	 *
	 * @param int    $post_id Book post ID.
	 * @param string $value   Barcode value to render.
	 */
	private function render_image( int $post_id, string $value ): bool {
		$directory = $this->directory();

		if ( ! wp_mkdir_p( $directory ) ) {
			$this->logger->error( 'Could not create the barcode directory.', array( 'directory' => $directory ) );

			return false;
		}

		$this->ensure_index_file( $directory );

		try {
			$svg = $this->generator->generate_svg( $value );
		} catch ( BarcodeException $exception ) {
			$this->logger->error(
				'Barcode generation failed.',
				array(
					'post_id' => $post_id,
					'value'   => $value,
					'message' => $exception->getMessage(),
				)
			);

			return false;
		}

		$file_path = trailingslashit( $directory ) . $post_id . '.svg';

		// Failure is already handled via the return-value check below; the
		// "@" only suppresses the redundant native PHP warning.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === @file_put_contents( $file_path, $svg ) ) {
			$this->logger->error( 'Could not write the barcode image file.', array( 'file' => $file_path ) );

			return false;
		}

		$directory_url = trailingslashit( wp_upload_dir()['baseurl'] ) . self::DIRECTORY_NAME;

		update_post_meta( $post_id, self::META_IMAGE_URL, trailingslashit( $directory_url ) . $post_id . '.svg' );

		return true;
	}

	/**
	 * Guard against directory listing, matching Services\Logger's pattern.
	 *
	 * @param string $directory Absolute path to the directory to guard.
	 */
	private function ensure_index_file( string $directory ): void {
		$index_file = trailingslashit( $directory ) . 'index.php';

		if ( ! file_exists( $index_file ) ) {
			// Best-effort directory-listing guard; a failure here is not
			// worth surfacing, matching Services\Logger's own suppression.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
			@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}
	}
}
