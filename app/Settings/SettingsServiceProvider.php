<?php
/**
 * Settings service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;

/**
 * Binds the settings repository and registers it with the WordPress
 * Settings API so any future settings screen gets capability checks,
 * nonces, and sanitization for free.
 */
final class SettingsServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton( Settings::class, static fn (): Settings => new Settings() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function boot( ContainerInterface $container ): void {
		add_action(
			'admin_init',
			static function () use ( $container ): void {
				$settings = $container->make( Settings::class );

				register_setting(
					Settings::OPTION_GROUP,
					Settings::option_name(),
					array(
						'type'              => 'array',
						'sanitize_callback' => array( $settings, 'sanitize' ),
						'default'           => array(),
					)
				);
			}
		);
	}
}
