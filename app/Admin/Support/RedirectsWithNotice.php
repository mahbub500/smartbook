<?php
/**
 * Shared "redirect back with a one-time notice" behaviour for admin pages.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Support;

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
	 * @param string               $type        "error" or "success".
	 * @param string               $message     Notice text.
	 * @param array<string, mixed> $extra_args  Additional query args to carry over (e.g.
	 *                                           EditBookPage's "book_id", so the page it
	 *                                           redirects back to still knows which book).
	 */
	private function redirect_with_notice( string $type, string $message, array $extra_args = array() ): never {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					$extra_args,
					array(
						'page'           => $this->notice_page_slug(),
						'sb_notice'      => rawurlencode( $message ),
						'sb_notice_type' => $type,
					)
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
		if ( ! isset( $_GET['sb_notice'] ) ) {
			return null;
		}

		$type = isset( $_GET['sb_notice_type'] ) && 'success' === $_GET['sb_notice_type'] ? 'success' : 'error';

		return array(
			'type'    => $type,
			'message' => sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['sb_notice'] ) ) ),
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
