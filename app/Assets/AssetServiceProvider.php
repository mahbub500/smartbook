<?php
/**
 * Asset service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;

/**
 * Binds and boots the admin and frontend asset loaders.
 */
final class AssetServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton( AdminAssetLoader::class, static fn (): AdminAssetLoader => new AdminAssetLoader() );
		$container->singleton( FrontendAssetLoader::class, static fn (): FrontendAssetLoader => new FrontendAssetLoader() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function boot( ContainerInterface $container ): void {
		$container->make( AdminAssetLoader::class )->register_hooks();
		$container->make( FrontendAssetLoader::class )->register_hooks();
	}
}
