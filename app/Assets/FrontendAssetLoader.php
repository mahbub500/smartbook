<?php
/**
 * Frontend asset loader.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Assets;

use SmartBook\Core\Contracts\Hookable;

use function sb_asset_url;
use function sb_asset_version;

/**
 * Enqueues SmartBook's public stylesheet and script on the frontend.
 */
final class FrontendAssetLoader implements Hookable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the public assets.
	 */
	public function enqueue(): void {
		/**
		 * Filters whether SmartBook's frontend assets should be enqueued
		 * on the current request, e.g. to restrict loading to templates
		 * that actually render SmartBook content.
		 *
		 * @param bool $should_enqueue Whether to enqueue. Defaults to true.
		 */
		if ( ! (bool) apply_filters( 'sb_should_enqueue_frontend_assets', true ) ) {
			return;
		}

		wp_enqueue_style(
			'sb-public',
			sb_asset_url( 'css/sb-public.css' ),
			array(),
			sb_asset_version( 'css/sb-public.css' )
		);

		wp_enqueue_script(
			'sb-public',
			sb_asset_url( 'js/sb-public.js' ),
			array(),
			sb_asset_version( 'js/sb-public.js' ),
			true
		);

		wp_localize_script(
			'sb-public',
			'sbPublic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => esc_url_raw( rest_url( 'sb/v1' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
