<?php
/**
 * The SmartBook admin dashboard page.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Pages;

use SmartBook\Admin\AdminMenu;
use SmartBook\Core\Contracts\Hookable;
use SmartBook\PostTypes\BookPostType;
use SmartBook\Services\BookStats;

use function sb_asset_url;
use function sb_asset_version;
use function sb_option;

/**
 * Renders a snapshot of the library: six at-a-glance stat cards plus
 * four Chart.js charts (books per year, per genre, per author, and
 * monthly reading activity), backed by real data from BookStats.
 *
 * Chart.js itself and this page's chart-init script are only enqueued
 * on this one screen (register_hooks()), not sitewide, since the
 * library is ~200KB and no other admin page needs it.
 */
final class DashboardPage implements Hookable {

	/**
	 * @param BookStats $stats Book catalog statistics.
	 */
	public function __construct( private readonly BookStats $stats ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_charts' ) );
	}

	/**
	 * Enqueue Chart.js and this page's chart-init script, only when the
	 * current screen is the SmartBook dashboard.
	 */
	public function enqueue_charts(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( AdminMenu::PARENT_SLUG !== $page ) {
			return;
		}

		wp_enqueue_script(
			'sb-chartjs',
			sb_asset_url( 'js/vendor/chart.umd.min.js' ),
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_script(
			'sb-dashboard',
			sb_asset_url( 'js/sb-dashboard.js' ),
			array( 'sb-chartjs' ),
			sb_asset_version( 'js/sb-dashboard.js' ),
			true
		);

		wp_localize_script( 'sb-dashboard', 'sbDashboardCharts', $this->chart_data() );
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( BookPostType::CAP_EDIT_BOOKS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smartbook' ) );
		}

		echo '<div class="wrap sb-admin-page">';
		printf( '<h1>%s</h1>', esc_html__( 'SmartBook Dashboard', 'smartbook' ) );

		$reading_tracker_enabled = sb_option( 'enable_reading_tracker', true );
		$borrow_enabled          = sb_option( 'enable_borrow', true );

		echo '<div class="sb-dashboard__cards">';
		$this->render_card( __( 'Total Books', 'smartbook' ), $this->stats->total() );

		if ( $reading_tracker_enabled ) {
			$this->render_card( __( 'Reading', 'smartbook' ), $this->stats->count_by_status( 'reading' ) );
			$this->render_card( __( 'Completed', 'smartbook' ), $this->stats->count_by_status( 'read' ) );
		}

		$this->render_card( __( 'Wishlist', 'smartbook' ), $this->stats->count_by_flag( 'sb_wishlist' ) );
		$this->render_card( __( 'Favorites', 'smartbook' ), $this->stats->count_by_flag( 'sb_favorite' ) );

		if ( $borrow_enabled ) {
			$this->render_card( __( 'Borrowed', 'smartbook' ), $this->stats->count_active_borrows() );
		}

		echo '</div>';

		if ( $borrow_enabled ) {
			$this->render_borrow_reminders();
		}

		printf( '<h2>%s</h2>', esc_html__( 'Charts', 'smartbook' ) );
		echo '<div class="sb-dashboard__charts">';
		$this->render_chart_card( 'sb-chart-books-per-year', __( 'Books Per Year', 'smartbook' ) );
		$this->render_chart_card( 'sb-chart-books-per-genre', __( 'Books Per Genre', 'smartbook' ) );
		$this->render_chart_card( 'sb-chart-books-per-author', __( 'Books Per Author', 'smartbook' ) );

		if ( $reading_tracker_enabled ) {
			$this->render_chart_card( 'sb-chart-monthly-reading', __( 'Monthly Reading', 'smartbook' ) );
		}

		echo '</div>';

		printf( '<h2>%s</h2>', esc_html__( 'Quick Links', 'smartbook' ) );
		echo '<ul class="sb-dashboard__links">';
		$this->render_link( __( 'Add New Book', 'smartbook' ), admin_url( 'post-new.php?post_type=' . BookPostType::SLUG ) );
		$this->render_link( __( 'View All Books', 'smartbook' ), admin_url( 'admin.php?page=sb_books' ) );
		$this->render_link( __( 'View Statistics', 'smartbook' ), admin_url( 'admin.php?page=sb_statistics' ) );
		$this->render_link( __( 'Import / Export', 'smartbook' ), admin_url( 'admin.php?page=sb_import_export' ) );
		echo '</ul>';

		echo '</div>';
	}

	/**
	 * Render a single stat card.
	 */
	private function render_card( string $label, int $value ): void {
		printf(
			'<div class="sb-card"><span class="sb-card__value">%s</span><span class="sb-card__label">%s</span></div>',
			esc_html( (string) $value ),
			esc_html( $label )
		);
	}

	/**
	 * Render the borrow-management reminders table: lost copies, overdue
	 * loans, and loans whose reminder date has arrived (see
	 * BookStats::borrow_alerts()).
	 */
	private function render_borrow_reminders(): void {
		$alerts = $this->stats->borrow_alerts();

		printf( '<h2>%s</h2>', esc_html__( 'Borrow Reminders', 'smartbook' ) );

		if ( array() === $alerts ) {
			printf( '<p>%s</p>', esc_html__( 'No overdue, lost, or reminder-triggered borrows.', 'smartbook' ) );

			return;
		}

		echo '<table class="widefat striped sb-stats-table sb-reminders-table">';
		echo '<thead><tr>';

		foreach ( array( __( 'Book', 'smartbook' ), __( 'Borrowed To', 'smartbook' ), __( 'Date', 'smartbook' ), __( 'Status', 'smartbook' ) ) as $header ) {
			printf( '<th>%s</th>', esc_html( $header ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $alerts as $alert ) {
			printf(
				'<tr><td><a href="%1$s">%2$s</a></td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
				esc_url( (string) get_edit_post_link( $alert['post_id'] ) ),
				esc_html( $alert['title'] ),
				esc_html( '' !== $alert['borrowed_to'] ? $alert['borrowed_to'] : '—' ),
				esc_html( '' !== $alert['date'] ? $alert['date'] : '—' ),
				$this->status_badge( $alert['status'] )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Build a status badge for one borrow_alerts() row.
	 */
	private function status_badge( string $status ): string {
		$labels = array(
			'overdue'  => __( 'Overdue', 'smartbook' ),
			'lost'     => __( 'Lost', 'smartbook' ),
			'reminder' => __( 'Reminder', 'smartbook' ),
		);

		return sprintf(
			'<span class="sb-badge sb-badge--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $labels[ $status ] ?? $status )
		);
	}

	/**
	 * Render a single chart card: a title plus the canvas Chart.js will
	 * render into.
	 */
	private function render_chart_card( string $canvas_id, string $title ): void {
		printf(
			'<div class="sb-chart-card"><h3 class="sb-chart-card__title">%1$s</h3><div class="sb-chart-card__canvas-wrap"><canvas id="%2$s"></canvas></div></div>',
			esc_html( $title ),
			esc_attr( $canvas_id )
		);
	}

	/**
	 * Render a single quick-link list item.
	 */
	private function render_link( string $label, string $url ): void {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Build the labels/data payload for every chart, localized to the
	 * "sbDashboardCharts" JS object that sb-dashboard.js reads. Omits
	 * "monthlyReading" entirely when the reading tracker is disabled;
	 * sb-dashboard.js's sb_renderChart() already no-ops when a dataset is
	 * missing, so this alone is enough to also skip that canvas.
	 *
	 * @return array<string, array{labels: string[], data: int[]}>
	 */
	private function chart_data(): array {
		$charts = array(
			'booksPerYear'   => $this->to_chart_dataset( $this->stats->books_per_year() ),
			'booksPerGenre'  => $this->to_chart_dataset( $this->stats->books_per_genre() ),
			'booksPerAuthor' => $this->to_chart_dataset( $this->stats->books_per_author() ),
		);

		if ( sb_option( 'enable_reading_tracker', true ) ) {
			$charts['monthlyReading'] = $this->to_chart_dataset( $this->stats->monthly_reading() );
		}

		return $charts;
	}

	/**
	 * Convert a "label => count" array into Chart.js's labels/data shape.
	 *
	 * @param array<string, int> $counts Label => count pairs.
	 *
	 * @return array{labels: string[], data: int[]}
	 */
	private function to_chart_dataset( array $counts ): array {
		return array(
			'labels' => array_map( 'strval', array_keys( $counts ) ),
			'data'   => array_values( $counts ),
		);
	}
}
