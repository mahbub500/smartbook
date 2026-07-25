<?php
/**
 * Frontend service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;

/**
 * Binds and boots every frontend-facing display class the plugin defines.
 */
final class FrontendServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton( BookContentDisplay::class, static fn (): BookContentDisplay => new BookContentDisplay() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function boot( ContainerInterface $container ): void {
		$container->make( BookContentDisplay::class )->register_hooks();
	}
}
