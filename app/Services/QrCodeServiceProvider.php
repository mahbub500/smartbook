<?php
/**
 * QR code service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Services;

use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;
use SmartBook\PostTypes\BookPostType;
use WP_Post;

/**
 * Binds the QR code generator/manager and wires their lifecycle hooks:
 * generate automatically when a book is saved, clean up the stored file
 * when a book is permanently deleted, and handle the manual "Regenerate"
 * action from QrCodeMetaBox.
 */
final class QrCodeServiceProvider extends AbstractServiceProvider {

	/**
	 * admin-post.php action name for the manual regenerate button.
	 */
	public const REGENERATE_ACTION = 'sb_regenerate_qr_code';

	/**
	 * {@inheritDoc}
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton( QrCodeGenerator::class, static fn (): QrCodeGenerator => new QrCodeGenerator() );

		$container->singleton(
			QrCodeManager::class,
			static fn ( ContainerInterface $container ): QrCodeManager => new QrCodeManager(
				$container->make( QrCodeGenerator::class ),
				$container->make( LoggerInterface::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( ContainerInterface $container ): void {
		$manager = $container->make( QrCodeManager::class );

		add_action(
			'save_post_' . BookPostType::SLUG,
			static function ( int $post_id, WP_Post $post ) use ( $manager ): void {
				if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
					return;
				}

				if ( ! in_array( $post->post_status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
					return;
				}

				$manager->ensure_generated( $post_id );
			},
			20,
			2
		);

		add_action(
			'delete_post',
			static function ( int $post_id ) use ( $manager ): void {
				$post = get_post( $post_id );

				if ( $post instanceof WP_Post && BookPostType::SLUG === $post->post_type ) {
					$manager->delete_for_post( $post_id );
				}
			}
		);

		add_action(
			'admin_post_' . self::REGENERATE_ACTION,
			static function () use ( $manager ): void {
				$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

				if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
					wp_die( esc_html__( 'You do not have permission to perform this action.', 'smartbook' ) );
				}

				check_admin_referer( 'sb_regenerate_qr_code_' . $post_id );

				$manager->regenerate( $post_id );

				wp_safe_redirect( (string) get_edit_post_link( $post_id, 'raw' ) );
				exit;
			}
		);
	}
}
