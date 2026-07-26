<?php
/**
 * Front-end "request to borrow" handler.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Frontend;

use SmartBook\Core\Contracts\Hookable;
use SmartBook\PostTypes\BookPostType;

use function sb_option;

/**
 * Lets any logged-in visitor ask to borrow an available book from its
 * own front-end page (see BookContentDisplay::render_availability()
 * for the button this posts from); the actual loan only starts once an
 * admin approves the request from Admin\Pages\BorrowedBooksPage's
 * "Requests" tab -- this class only ever records the request itself
 * (sb_borrow_request_user/sb_borrow_request_date), it never sets
 * "sb_borrowed" directly.
 *
 * Registered on "admin_post_{action}" only, not the "_nopriv_" variant,
 * since WordPress routes a submission to exactly one of the two based
 * on login state -- omitting the nopriv hook means a logged-out
 * submission (which shouldn't happen anyway, since the form is only
 * ever rendered for a logged-in visitor) simply has no handler to run,
 * rather than needing to reject it here.
 */
final class BorrowRequestController implements Hookable {

	/**
	 * admin-post.php action name.
	 */
	public const ACTION = 'sb_request_borrow';

	/**
	 * Nonce hidden field name.
	 */
	public const NONCE_NAME = 'sb_request_borrow_nonce';

	/**
	 * Nonce action name prefix, suffixed with the book's post id.
	 */
	private const NONCE_ACTION_PREFIX = 'sb_request_borrow_';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		if ( sb_option( 'enable_borrow', true ) ) {
			add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_request' ) );
		}
	}

	/**
	 * Nonce action name for a given book, so BookContentDisplay's form
	 * and this handler always agree on it.
	 */
	public static function nonce_action( int $post_id ): string {
		return self::NONCE_ACTION_PREFIX . $post_id;
	}

	/**
	 * Process the "Request to Borrow" form submission.
	 */
	public function handle_request(): void {
		$post_id = isset( $_POST['sb_post_id'] ) ? absint( $_POST['sb_post_id'] ) : 0;

		if ( $post_id <= 0 || BookPostType::SLUG !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'Invalid book.', 'smartbook' ) );
		}

		check_admin_referer( self::nonce_action( $post_id ), self::NONCE_NAME );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to request a book.', 'smartbook' ) );
		}

		if ( $this->is_borrowed( $post_id ) ) {
			$this->redirect_back( $post_id, 'unavailable' );
		}

		if ( $this->has_pending_request( $post_id ) ) {
			$this->redirect_back( $post_id, 'already_requested' );
		}

		update_post_meta( $post_id, 'sb_borrow_request_user', get_current_user_id() );
		update_post_meta( $post_id, 'sb_borrow_request_date', current_time( 'mysql' ) );

		$this->redirect_back( $post_id, 'requested' );
	}

	/**
	 * Whether a book is currently on loan (not available to request).
	 */
	private function is_borrowed( int $post_id ): bool {
		return '1' === (string) get_post_meta( $post_id, 'sb_borrowed', true )
			&& '1' !== (string) get_post_meta( $post_id, 'sb_returned', true );
	}

	/**
	 * Whether a book already has an unresolved pending request.
	 */
	private function has_pending_request( int $post_id ): bool {
		return (int) get_post_meta( $post_id, 'sb_borrow_request_user', true ) > 0;
	}

	/**
	 * Redirect back to the book's own page, flagged with a notice
	 * BookContentDisplay turns into a banner.
	 */
	private function redirect_back( int $post_id, string $notice ): never {
		$url = add_query_arg( array( 'sb_borrow_notice' => $notice ), get_permalink( $post_id ) );

		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}
}
