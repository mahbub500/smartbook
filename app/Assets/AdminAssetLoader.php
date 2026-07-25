<?php
/**
 * Admin asset loader.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Assets;

use SmartBook\Core\Contracts\Hookable;

use function sb_asset_url;
use function sb_asset_version;

/**
 * Enqueues SmartBook's admin stylesheet and script, restricted to
 * SmartBook's own admin screens so other screens in wp-admin do not pay
 * for assets they never use.
 *
 * Other modules register their screens via the "sb_admin_asset_screens"
 * filter (either the raw $hook_suffix or, for custom post type screens,
 * the WP_Screen id) instead of this class needing to know about them.
 */
final class AdminAssetLoader implements Hookable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the admin assets when the current screen belongs to SmartBook.
	 *
	 * @param string $hook_suffix Current admin page hook suffix, supplied by WordPress.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! $this->should_enqueue( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			'sb-admin',
			sb_asset_url( 'css/sb-admin.css' ),
			array(),
			sb_asset_version( 'css/sb-admin.css' )
		);

		wp_enqueue_script(
			'sb-admin',
			sb_asset_url( 'js/sb-admin.js' ),
			array(),
			sb_asset_version( 'js/sb-admin.js' ),
			true
		);

		wp_localize_script(
			'sb-admin',
			'sbAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sb_admin_nonce' ),
			)
		);
	}

	/**
	 * Whether the current admin screen should receive SmartBook's assets.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	private function should_enqueue( string $hook_suffix ): bool {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = null !== $screen ? (string) $screen->id : '';

		/**
		 * Filters the list of admin hook suffixes / screen ids that should
		 * receive SmartBook's admin assets.
		 *
		 * @param string[] $screens Allowed hook suffixes and/or screen ids.
		 */
		$allowed_screens = (array) apply_filters( 'sb_admin_asset_screens', array() );

		if ( in_array( $hook_suffix, $allowed_screens, true ) ) {
			return true;
		}

		if ( '' !== $screen_id && in_array( $screen_id, $allowed_screens, true ) ) {
			return true;
		}

		return '' !== $screen_id && str_starts_with( $screen_id, 'sb_' );
	}
}
