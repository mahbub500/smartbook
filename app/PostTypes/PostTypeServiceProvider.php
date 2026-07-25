<?php
/**
 * Post type service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\PostTypes;

use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;

/**
 * Binds and boots every custom post type the plugin registers.
 */
final class PostTypeServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton( BookPostType::class, static fn (): BookPostType => new BookPostType() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( ContainerInterface $container ): void {
		$container->make( BookPostType::class )->register_hooks();
	}
}
