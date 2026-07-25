<?php
/**
 * Plugin activation handler.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core;

/**
 * Runs once when the plugin is activated.
 */
final class Activator {

	/**
	 * Default option values seeded on first activation.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_OPTIONS = array(
		'enable_logging' => true,
		'log_level'      => 'error',
	);

	/**
	 * Activation entry point registered via register_activation_hook().
	 */
	public static function activate(): void {
		if ( ! Requirements::satisfied() ) {
			deactivate_plugins( SB_BASENAME );

			wp_die(
				esc_html( Requirements::unsatisfied_message() ),
				esc_html__( 'Plugin activation error', 'smartbook' ),
				array( 'back_link' => true )
			);
		}

		self::create_default_options();
		self::maybe_upgrade();

		flush_rewrite_rules();
	}

	/**
	 * Seed default options on first-ever activation.
	 */
	private static function create_default_options(): void {
		if ( false === get_option( 'sb_options' ) ) {
			add_option( 'sb_options', self::DEFAULT_OPTIONS );
		}

		if ( false === get_option( 'sb_db_version' ) ) {
			add_option( 'sb_db_version', SB_VERSION );
		}
	}

	/**
	 * Run schema/data migrations when reactivating an older install.
	 */
	private static function maybe_upgrade(): void {
		$installed = (string) get_option( 'sb_db_version', '0' );

		if ( version_compare( $installed, SB_VERSION, '<' ) ) {
			// Place versioned schema/data migrations here as the plugin evolves.
			update_option( 'sb_db_version', SB_VERSION );
		}
	}
}
