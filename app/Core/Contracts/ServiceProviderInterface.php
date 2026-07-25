<?php
/**
 * Service provider contract.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A service provider wires one module (Settings, Assets, PostTypes, ...)
 * into the plugin: register() binds services into the container, boot()
 * attaches WordPress hooks. Splitting the two phases guarantees every
 * provider has finished registering its bindings before any provider
 * starts booting, so boot-time code can safely depend on bindings made
 * by other providers.
 */
interface ServiceProviderInterface {

	/**
	 * Bind services into the container. Must not call WordPress hook
	 * functions here; use boot() for that.
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function register( ContainerInterface $container ): void;

	/**
	 * Attach WordPress hooks and perform any work that depends on other
	 * providers already having registered their bindings.
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function boot( ContainerInterface $container ): void;
}
