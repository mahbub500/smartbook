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

		echo '<div class="sb-dashboard__cards">';
		$this->render_card( __( 'Total Books', 'smartbook' ), $this->stats->total() );
		$this->render_card( __( 'Reading', 'smartbook' ), $this->stats->count_by_status( 'reading' ) );
		$this->render_card( __( 'Completed', 'smartbook' ), $this->stats->count_by_status( 'read' ) );
		$this->render_card( __( 'Wishlist', 'smartbook' ), $this->stats->count_by_flag( 'sb_wishlist' ) );
		$this->render_card( __( 'Favorites', 'smartbook' ), $this->stats->count_by_flag( 'sb_favorite' ) );
		$this->render_card( __( 'Borrowed', 'smartbook' ), $this->stats->count_by_flag( 'sb_borrowed' ) );
		echo '</div>';

		printf( '<h2>%s</h2>', esc_html__( 'Charts', 'smartbook' ) );
		echo '<div class="sb-dashboard__charts">';
		$this->render_chart_card( 'sb-chart-books-per-year', __( 'Books Per Year', 'smartbook' ) );
		$this->render_chart_card( 'sb-chart-books-per-genre', __( 'Books Per Genre', 'smartbook' ) );
		$this->render_chart_card( 'sb-chart-books-per-author', __( 'Books Per Author', 'smartbook' ) );
		$this->render_chart_card( 'sb-chart-monthly-reading', __( 'Monthly Reading', 'smartbook' ) );
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
	 * Build the labels/data payload for all four charts, localized to
	 * the "sbDashboardCharts" JS object that sb-dashboard.js reads.
	 *
	 * @return array<string, array{labels: string[], data: int[]}>
	 */
	private function chart_data(): array {
		return array(
			'booksPerYear'   => $this->to_chart_dataset( $this->stats->books_per_year() ),
			'booksPerGenre'  => $this->to_chart_dataset( $this->stats->books_per_genre() ),
			'booksPerAuthor' => $this->to_chart_dataset( $this->stats->books_per_author() ),
			'monthlyReading' => $this->to_chart_dataset( $this->stats->monthly_reading() ),
		);
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
