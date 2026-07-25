<?php
/**
 * Shared "redirect back with a one-time notice" behaviour for admin pages.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Several admin pages process an action (import, export, bulk edit,
 * trash/delete) and then need to redirect back to themselves with a
 * result message, since re-submitting a POST on refresh must be
 * avoided. This trait centralizes that pattern so it is implemented
 * once instead of once per page.
 */
trait RedirectsWithNotice {

	/**
	 * The admin page slug to redirect back to; implemented by the class
	 * using this trait.
	 */
	abstract private function notice_page_slug(): string;

	/**
	 * Redirect back to the page with a result notice, then terminate the
	 * request (standard for a POST/admin-post.php handler).
	 *
	 * @param string $type    Notice type, "success" or "error".
	 * @param string $message Notice message to display.
	 */
	private function redirect_with_notice( string $type, string $message ): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => $this->notice_page_slug(),
					'sb_notice'      => rawurlencode( $message ),
					'sb_notice_type' => $type,
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Read a one-time result notice from the query string.
	 *
	 * @return array{type: string, message: string}|null
	 */
	private function consume_notice(): ?array {
		// Read-only: this is this same class's own one-time notice, set by
		// its own prior redirect_with_notice() call, not attacker input
		// acted upon; only ever echoed back, escaped, to the same user.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['sb_notice'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $_GET['sb_notice_type'] ) && 'success' === $_GET['sb_notice_type'] ? 'success' : 'error';

		return array(
			'type'    => $type,
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_text_field() is the outermost call and sanitizes the final, fully-decoded value.
			'message' => sanitize_text_field( rawurldecode( wp_unslash( (string) $_GET['sb_notice'] ) ) ),
		);
	}

	/**
	 * Render the pending notice, if any, escaping it at the point of output.
	 */
	private function render_notice(): void {
		$notice = $this->consume_notice();

		if ( null === $notice ) {
			return;
		}

		printf(
			'<div class="sb-notice sb-notice--%1$s"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ),
			esc_html( $notice['message'] )
		);
	}
}
