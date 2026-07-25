<?php
/**
 * Logger service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;

/**
 * Binds the logger as a shared instance, configured from the plugin's
 * stored options so it never needs to know about the Settings module.
 */
final class LoggerServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton(
			LoggerInterface::class,
			static function (): Logger {
				$options = get_option( 'sb_options', array() );
				$options = is_array( $options ) ? $options : array();

				$enabled   = (bool) ( $options['enable_logging'] ?? true );
				$threshold = (string) ( $options['log_level'] ?? 'error' );
				$directory = trailingslashit( wp_upload_dir()['basedir'] ) . 'sb-logs';

				return new Logger( $directory, $enabled, $threshold );
			}
		);

		$container->bind( Logger::class, static fn ( ContainerInterface $container ): mixed => $container->make( LoggerInterface::class ) );
	}
}
