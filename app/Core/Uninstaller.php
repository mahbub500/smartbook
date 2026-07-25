<?php
/**
 * Plugin uninstall handler.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core;

/**
 * Permanently removes all data owned by the plugin. Invoked only from
 * uninstall.php, never on plain deactivation.
 */
final class Uninstaller {

	/**
	 * Uninstall entry point invoked from uninstall.php.
	 */
	public static function uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::delete_options();
		self::delete_transients();
		self::clear_scheduled_events();

		if ( is_multisite() ) {
			self::uninstall_for_network();
		}
	}

	/**
	 * Remove all options owned by the plugin on the current site.
	 */
	private static function delete_options(): void {
		$options = array(
			'sb_options',
			'sb_db_version',
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Remove cached transients owned by the plugin.
	 */
	private static function delete_transients(): void {
		delete_transient( 'sb_cache' );
	}

	/**
	 * Clear any scheduled cron events owned by the plugin.
	 */
	private static function clear_scheduled_events(): void {
		wp_clear_scheduled_hook( 'sb_cron_event' );
	}

	/**
	 * Repeat the per-site cleanup across every site in a multisite network.
	 */
	private static function uninstall_for_network(): void {
		$site_ids = get_sites( array( 'fields' => 'ids' ) );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );

			self::delete_options();
			self::delete_transients();
			self::clear_scheduled_events();

			restore_current_blog();
		}
	}
}
