<?php
/**
 * Plugin Name:       SmartBook
 * Plugin URI:        https://example.com/smartbook
 * Description:       A modular, production-ready WordPress plugin scaffold built on SOLID, PSR-4 and dependency injection principles.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            SmartBook
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smartbook
 * Domain Path:       /languages
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------
// Core plugin constants.
// -----------------------------------------------------------------------
define( 'SB_VERSION', '1.0.0' );
define( 'SB_FILE', __FILE__ );
define( 'SB_PATH', plugin_dir_path( __FILE__ ) );
define( 'SB_URL', plugin_dir_url( __FILE__ ) );
define( 'SB_BASENAME', plugin_basename( __FILE__ ) );
define( 'SB_MIN_PHP', '8.2' );
define( 'SB_MIN_WP', '6.4' );

// -----------------------------------------------------------------------
// Composer autoloader.
// -----------------------------------------------------------------------
$sb_autoloader = SB_PATH . 'vendor/autoload.php';

if ( ! file_exists( $sb_autoloader ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'SmartBook: missing Composer dependencies. Run "composer install" in the plugin directory.', 'smartbook' )
			);
		}
	);

	return;
}

require_once $sb_autoloader;

unset( $sb_autoloader );

// -----------------------------------------------------------------------
// Lifecycle hooks. These must be registered at the top level of the main
// file, never from inside another hook callback.
// -----------------------------------------------------------------------
register_activation_hook( SB_FILE, array( Core\Activator::class, 'activate' ) );
register_deactivation_hook( SB_FILE, array( Core\Deactivator::class, 'deactivate' ) );

// -----------------------------------------------------------------------
// Boot the plugin once all plugins have loaded. Boot failures are caught
// so a single misbehaving module cannot white-screen the entire site;
// the error is logged and surfaced as an admin notice instead.
// -----------------------------------------------------------------------
add_action(
	'plugins_loaded',
	static function (): void {
		try {
			Core\Plugin::instance()->boot();
		} catch ( \Throwable $throwable ) {
			// The container/logger may not be available at this point,
			// so fall back to PHP's own error log.
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf( 'SmartBook boot failure: %s', $throwable->getMessage() )
			);

			add_action(
				'admin_notices',
				static function () use ( $throwable ): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %s: error message. */
								__( 'SmartBook failed to start: %s', 'smartbook' ),
								$throwable->getMessage()
							)
						)
					);
				}
			);
		}
	}
);
