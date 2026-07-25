<?php
/**
 * Post type service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;

/**
 * Binds and boots every custom post type the plugin registers.
 */
final class PostTypeServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton( BookPostType::class, static fn (): BookPostType => new BookPostType() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function boot( ContainerInterface $container ): void {
		$container->make( BookPostType::class )->register_hooks();
	}
}
