<?php
/**
 * Meta box service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\MetaBoxes;

use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;
use SmartBook\Services\QrCodeManager;

/**
 * Binds and boots every meta box the plugin registers.
 */
final class MetaBoxServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton( BookDetailsMetaBox::class, static fn (): BookDetailsMetaBox => new BookDetailsMetaBox() );

		$container->singleton(
			QrCodeMetaBox::class,
			static fn ( ContainerInterface $container ): QrCodeMetaBox => new QrCodeMetaBox( $container->make( QrCodeManager::class ) )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( ContainerInterface $container ): void {
		$container->make( BookDetailsMetaBox::class )->register_hooks();
		$container->make( QrCodeMetaBox::class )->register_hooks();
	}
}
