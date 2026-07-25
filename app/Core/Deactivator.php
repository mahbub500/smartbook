<?php
/**
 * Plugin deactivation handler.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core;

/**
 * Runs every time the plugin is deactivated. Must not delete user data;
 * that is the responsibility of the uninstall handler.
 */
final class Deactivator {

	/**
	 * Deactivation entry point registered via register_deactivation_hook().
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'sb_cron_event' );
		flush_rewrite_rules();
	}
}
