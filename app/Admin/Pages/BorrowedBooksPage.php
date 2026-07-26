<?php
/**
 * The SmartBook borrowed books management page.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Pages;

use SmartBook\Admin\Support\RedirectsWithNotice;
use SmartBook\Core\Contracts\Hookable;
use SmartBook\PostTypes\BookPostType;
use SmartBook\Services\BookStats;
use WP_User;

use function sb_format_date;

/**
 * A dedicated list of every borrowed book and who has it, independent of
 * BooksPage's general catalog table: filterable by loan status ("On
 * Loan", "Returned", "All"), plus a "Requests" tab listing every pending
 * "request to borrow" a front-end visitor submitted (see
 * Frontend\BorrowRequestController) with one-click Approve/Deny actions.
 * Approving is the only place "sb_borrowed"/"sb_borrowed_to"/
 * "sb_borrow_date" actually get set as a result of a request -- the
 * front-end submission itself only ever records the request, never the
 * loan. Data comes from BookStats' borrowed_books()/
 * pending_borrow_requests().
 */
final class BorrowedBooksPage implements Hookable {

	use RedirectsWithNotice;

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'sb_borrowed_books';

	/**
	 * admin-post.php action name for the "Mark Returned" row action.
	 */
	private const MARK_RETURNED_ACTION = 'sb_mark_returned';

	/**
	 * admin-post.php action name for the "Approve" request action.
	 */
	private const APPROVE_REQUEST_ACTION = 'sb_approve_borrow_request';

	/**
	 * admin-post.php action name for the "Deny" request action.
	 */
	private const DENY_REQUEST_ACTION = 'sb_deny_borrow_request';

	/**
	 * @param BookStats $stats Book catalog statistics, including borrowed_books()/pending_borrow_requests().
	 */
	public function __construct( private readonly BookStats $stats ) {
	}

	/**
	 * Number of pending borrow requests, for AdminMenu's sidebar
	 * notification bubble on this page's own menu entry.
	 */
	public function pending_request_count(): int {
		return $this->stats->count_pending_borrow_requests();
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_' . self::MARK_RETURNED_ACTION, array( $this, 'handle_mark_returned' ) );
		add_action( 'admin_post_' . self::APPROVE_REQUEST_ACTION, array( $this, 'handle_approve_request' ) );
		add_action( 'admin_post_' . self::DENY_REQUEST_ACTION, array( $this, 'handle_deny_request' ) );
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( BookPostType::CAP_EDIT_BOOKS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smartbook' ) );
		}

		$filter = $this->current_filter();

		echo '<div class="wrap sb-admin-page">';
		printf( '<h1>%s</h1>', esc_html__( 'Borrowed Books', 'smartbook' ) );

		$this->render_notice();
		$this->render_filter_tabs( $filter );

		echo '<div class="sb-panel">';

		if ( 'requests' === $filter ) {
			$this->render_requests_table();
		} else {
			$this->render_borrowed_table( $filter );
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Mark a book returned (sb_returned = '1') and redirect back with a
	 * result notice. Every other borrow field (borrowed_to, dates) is
	 * left as-is, as a historical record of the loan.
	 */
	public function handle_mark_returned(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		check_admin_referer( 'sb_mark_returned_' . $post_id );

		if ( $post_id <= 0 || BookPostType::SLUG !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'smartbook' ) );
		}

		update_post_meta( $post_id, 'sb_returned', '1' );

		$this->redirect_with_notice( 'success', __( 'Book marked as returned.', 'smartbook' ) );
	}

	/**
	 * Approve a pending "request to borrow": this is the one and only
	 * place the loan itself actually starts -- sets "sb_borrowed" (and
	 * clears "sb_returned", in case this book was previously returned),
	 * "sb_borrowed_to" (the requester's display name), and
	 * "sb_borrow_date" (today), then clears the request meta.
	 */
	public function handle_approve_request(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		check_admin_referer( 'sb_approve_borrow_request_' . $post_id );

		if ( $post_id <= 0 || BookPostType::SLUG !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'smartbook' ) );
		}

		$requester_id = (int) get_post_meta( $post_id, 'sb_borrow_request_user', true );

		if ( $requester_id <= 0 ) {
			$this->redirect_with_notice( 'error', __( 'That request no longer exists.', 'smartbook' ), array( 'status' => 'requests' ) );
		}

		$requester = get_userdata( $requester_id );

		update_post_meta( $post_id, 'sb_borrowed', '1' );
		update_post_meta( $post_id, 'sb_returned', '' );
		update_post_meta( $post_id, 'sb_borrowed_to', $requester instanceof WP_User ? $requester->display_name : '' );
		update_post_meta( $post_id, 'sb_borrow_date', current_time( 'Y-m-d' ) );
		delete_post_meta( $post_id, 'sb_borrow_request_user' );
		delete_post_meta( $post_id, 'sb_borrow_request_date' );

		$this->redirect_with_notice( 'success', __( 'Request approved; the book is now on loan.', 'smartbook' ), array( 'status' => 'requests' ) );
	}

	/**
	 * Deny a pending "request to borrow": clears the request meta only,
	 * the book stays available.
	 */
	public function handle_deny_request(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		check_admin_referer( 'sb_deny_borrow_request_' . $post_id );

		if ( $post_id <= 0 || BookPostType::SLUG !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'smartbook' ) );
		}

		delete_post_meta( $post_id, 'sb_borrow_request_user' );
		delete_post_meta( $post_id, 'sb_borrow_request_date' );

		$this->redirect_with_notice( 'success', __( 'Request denied.', 'smartbook' ), array( 'status' => 'requests' ) );
	}

	/**
	 * Render the "On Loan"/"Returned"/"All" table (borrowed_books()).
	 */
	private function render_borrowed_table( string $filter ): void {
		$books = $this->stats->borrowed_books( $filter );

		if ( array() === $books ) {
			printf( '<p class="sb-panel__empty">%s</p>', esc_html__( 'No borrowed books to show.', 'smartbook' ) );
			return;
		}

		echo '<table class="widefat striped sb-stats-table sb-borrowed-books-table">';
		echo '<thead><tr>';

		foreach ( $this->column_headers() as $header ) {
			printf( '<th>%s</th>', esc_html( $header ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $books as $book ) {
			$this->render_row( $book );
		}

		echo '</tbody></table>';
	}

	/**
	 * Render the "Requests" table (pending_borrow_requests()).
	 */
	private function render_requests_table(): void {
		$requests = $this->stats->pending_borrow_requests();

		if ( array() === $requests ) {
			printf( '<p class="sb-panel__empty">%s</p>', esc_html__( 'No pending borrow requests.', 'smartbook' ) );
			return;
		}

		echo '<table class="widefat striped sb-stats-table sb-borrowed-books-table">';
		echo '<thead><tr>';

		foreach ( array( __( 'Book', 'smartbook' ), __( 'Requested By', 'smartbook' ), __( 'Requested', 'smartbook' ), __( 'Actions', 'smartbook' ) ) as $header ) {
			printf( '<th>%s</th>', esc_html( $header ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $requests as $request ) {
			$this->render_request_row( $request );
		}

		echo '</tbody></table>';
	}

	/**
	 * Render one borrowed_books() row.
	 *
	 * @param array{post_id: int, title: string, borrowed_to: string, borrow_date: string, return_date: string, reminder_date: string, lost: bool, returned: bool, overdue: bool} $book Row data.
	 */
	private function render_row( array $book ): void {
		echo '<tr>';

		printf(
			'<td><a href="%1$s">%2$s</a></td>',
			esc_url( $this->edit_book_link( $book['post_id'] ) ),
			esc_html( $book['title'] )
		);

		printf( '<td>%s</td>', esc_html( '' !== $book['borrowed_to'] ? $book['borrowed_to'] : '—' ) );
		printf( '<td>%s</td>', esc_html( '' !== $book['borrow_date'] ? sb_format_date( $book['borrow_date'] ) : '—' ) );
		printf( '<td>%s</td>', esc_html( '' !== $book['return_date'] ? sb_format_date( $book['return_date'] ) : '—' ) );
		printf( '<td>%s</td>', $this->status_badge( $book ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped fragment, see status_badge().

		echo '<td>';

		if ( ! $book['returned'] ) {
			printf(
				'<a class="button button-small" href="%1$s">%2$s</a>',
				esc_url( $this->mark_returned_url( $book['post_id'] ) ),
				esc_html__( 'Mark Returned', 'smartbook' )
			);
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render one pending_borrow_requests() row.
	 *
	 * @param array{post_id: int, title: string, requester: string, requested_date: string} $request Row data.
	 */
	private function render_request_row( array $request ): void {
		echo '<tr>';

		printf(
			'<td><a href="%1$s">%2$s</a></td>',
			esc_url( $this->edit_book_link( $request['post_id'] ) ),
			esc_html( $request['title'] )
		);

		printf( '<td>%s</td>', esc_html( $request['requester'] ) );
		printf( '<td>%s</td>', esc_html( '' !== $request['requested_date'] ? sb_format_date( $request['requested_date'] ) : '—' ) );

		printf(
			'<td><a class="button button-primary button-small" href="%1$s">%2$s</a> <a class="button button-small" href="%3$s">%4$s</a></td>',
			esc_url( $this->approve_request_url( $request['post_id'] ) ),
			esc_html__( 'Approve', 'smartbook' ),
			esc_url( $this->deny_request_url( $request['post_id'] ) ),
			esc_html__( 'Deny', 'smartbook' )
		);

		echo '</tr>';
	}

	/**
	 * Build a status badge for one borrowed_books() row: "Returned",
	 * "Lost", "Overdue", or the default "On Loan".
	 */
	private function status_badge( array $book ): string {
		if ( $book['returned'] ) {
			return sprintf( '<span class="sb-badge sb-badge--returned">%s</span>', esc_html__( 'Returned', 'smartbook' ) );
		}

		if ( $book['lost'] ) {
			return sprintf( '<span class="sb-badge sb-badge--lost">%s</span>', esc_html__( 'Lost', 'smartbook' ) );
		}

		if ( $book['overdue'] ) {
			return sprintf( '<span class="sb-badge sb-badge--overdue">%s</span>', esc_html__( 'Overdue', 'smartbook' ) );
		}

		return sprintf( '<span class="sb-badge sb-badge--on_loan">%s</span>', esc_html__( 'On Loan', 'smartbook' ) );
	}

	/**
	 * Render the "Requests" / "On Loan" / "Returned" / "All" filter tabs,
	 * WordPress's own "subsubsub" list-table convention. "Requests"
	 * carries a count bubble (matching the sidebar menu's own, see
	 * AdminMenu::add_borrow_request_bubble()) whenever any are pending.
	 */
	private function render_filter_tabs( string $current ): void {
		$pending_count = $this->stats->count_pending_borrow_requests();

		$tabs = array(
			'requests' => $pending_count > 0
				? sprintf(
					/* translators: %d: number of pending borrow requests. */
					__( 'Requests (%d)', 'smartbook' ),
					$pending_count
				)
				: __( 'Requests', 'smartbook' ),
			'active'   => __( 'On Loan', 'smartbook' ),
			'returned' => __( 'Returned', 'smartbook' ),
			'all'      => __( 'All', 'smartbook' ),
		);
		$items = array();

		foreach ( $tabs as $key => $label ) {
			$items[] = sprintf(
				'<li><a href="%1$s"%2$s>%3$s</a></li>',
				esc_url(
					add_query_arg(
						array(
							'page'   => self::PAGE_SLUG,
							'status' => $key,
						),
						admin_url( 'admin.php' )
					)
				),
				$key === $current ? ' class="current"' : '',
				esc_html( $label )
			);
		}

		echo '<ul class="subsubsub">' . implode( ' | ', $items ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value already escaped above.
		echo '<br class="clear" />';
	}

	/**
	 * Table column headers for the borrowed-books table, in order.
	 *
	 * @return string[]
	 */
	private function column_headers(): array {
		return array(
			__( 'Book', 'smartbook' ),
			__( 'Borrowed To', 'smartbook' ),
			__( 'Borrow Date', 'smartbook' ),
			__( 'Return Date', 'smartbook' ),
			__( 'Status', 'smartbook' ),
			__( 'Actions', 'smartbook' ),
		);
	}

	/**
	 * The requested filter ("requests", "active", "returned", "all"),
	 * defaulting to "requests" for an unset or unrecognised value, so a
	 * fresh visit to the page leads with whatever needs a decision.
	 */
	private function current_filter(): string {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'requests'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $status, array( 'requests', 'active', 'returned', 'all' ), true ) ? $status : 'requests';
	}

	/**
	 * URL to the custom Edit Book page for a book.
	 */
	private function edit_book_link( int $post_id ): string {
		return add_query_arg(
			array(
				'page'    => 'sb_edit_book',
				'book_id' => $post_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Nonce-protected admin-post.php URL for the "Mark Returned" action.
	 */
	private function mark_returned_url( int $post_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::MARK_RETURNED_ACTION,
					'post_id' => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			'sb_mark_returned_' . $post_id
		);
	}

	/**
	 * Nonce-protected admin-post.php URL for the "Approve" request action.
	 */
	private function approve_request_url( int $post_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::APPROVE_REQUEST_ACTION,
					'post_id' => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			'sb_approve_borrow_request_' . $post_id
		);
	}

	/**
	 * Nonce-protected admin-post.php URL for the "Deny" request action.
	 */
	private function deny_request_url( int $post_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::DENY_REQUEST_ACTION,
					'post_id' => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			'sb_deny_borrow_request_' . $post_id
		);
	}

	/**
	 * {@inheritDoc}
	 */
	private function notice_page_slug(): string {
		return self::PAGE_SLUG;
	}
}
