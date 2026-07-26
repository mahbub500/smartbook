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

use function sb_format_date;

/**
 * A dedicated list of every borrowed book and who has it, independent of
 * BooksPage's general catalog table: filterable by loan status ("On
 * Loan", "Returned", "All") and offering a one-click "Mark Returned"
 * action per row, so clearing a returned loan doesn't require opening
 * that book's edit screen. Data comes from BookStats::borrowed_books();
 * marking a book returned is the only mutation this page performs.
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
	 * @param BookStats $stats Book catalog statistics, including borrowed_books().
	 */
	public function __construct( private readonly BookStats $stats ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_' . self::MARK_RETURNED_ACTION, array( $this, 'handle_mark_returned' ) );
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( BookPostType::CAP_EDIT_BOOKS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smartbook' ) );
		}

		$filter = $this->current_filter();
		$books  = $this->stats->borrowed_books( $filter );

		echo '<div class="wrap sb-admin-page">';
		printf( '<h1>%s</h1>', esc_html__( 'Borrowed Books', 'smartbook' ) );

		$this->render_notice();
		$this->render_filter_tabs( $filter );

		echo '<div class="sb-panel">';

		if ( array() === $books ) {
			printf( '<p class="sb-panel__empty">%s</p>', esc_html__( 'No borrowed books to show.', 'smartbook' ) );
			echo '</div></div>';

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
	 * Render the "On Loan" / "Returned" / "All" filter tabs, WordPress's
	 * own "subsubsub" list-table convention.
	 */
	private function render_filter_tabs( string $current ): void {
		$tabs  = array(
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
	 * Table column headers, in order.
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
	 * The requested filter ("active", "returned", "all"), defaulting to
	 * "active" for an unset or unrecognised value.
	 */
	private function current_filter(): string {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'active'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $status, array( 'active', 'returned', 'all' ), true ) ? $status : 'active';
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
	 * {@inheritDoc}
	 */
	private function notice_page_slug(): string {
		return self::PAGE_SLUG;
	}
}
