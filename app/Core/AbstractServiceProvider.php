<?php
/**
 * Base service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Core\Contracts\ContainerInterface;
use SmartBook\Core\Contracts\ServiceProviderInterface;

/**
 * Convenience base class for service providers.
 *
 * Most providers only need to bind services (register) or only need to
 * attach hooks (boot), rarely both in equal measure. Providing empty
 * default implementations means a concrete provider only has to override
 * the phase it actually uses.
 */
abstract class AbstractServiceProvider implements ServiceProviderInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function register( ContainerInterface $container ): void {
		// Intentionally empty; override to bind services.
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function boot( ContainerInterface $container ): void {
		// Intentionally empty; override to attach WordPress hooks.
	}
}
