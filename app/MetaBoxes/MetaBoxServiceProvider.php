<?php
/**
 * Meta box service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\MetaBoxes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;
use SmartBook\Services\BarcodeManager;
use SmartBook\Services\QrCodeManager;

/**
 * Binds and boots every meta box the plugin registers.
 */
final class MetaBoxServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton( BookDetailsMetaBox::class, static fn (): BookDetailsMetaBox => new BookDetailsMetaBox() );

		$container->singleton(
			QrCodeMetaBox::class,
			static fn ( ContainerInterface $container ): QrCodeMetaBox => new QrCodeMetaBox( $container->make( QrCodeManager::class ) )
		);

		$container->singleton(
			BarcodeMetaBox::class,
			static fn ( ContainerInterface $container ): BarcodeMetaBox => new BarcodeMetaBox( $container->make( BarcodeManager::class ) )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function boot( ContainerInterface $container ): void {
		$container->make( BookDetailsMetaBox::class )->register_hooks();
		$container->make( QrCodeMetaBox::class )->register_hooks();
		$container->make( BarcodeMetaBox::class )->register_hooks();
	}
}
