<?php
/**
 * The SmartBook statistics page.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Pages;

use SmartBook\PostTypes\BookPostType;
use SmartBook\Taxonomies\TaxonomyServiceProvider;

/**
 * Renders a breakdown of book counts by status and term counts for
 * every registered taxonomy.
 */
final class StatisticsPage {

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( BookPostType::CAP_EDIT_BOOKS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smartbook' ) );
		}

		echo '<div class="wrap sb-admin-page">';
		printf( '<h1>%s</h1>', esc_html__( 'SmartBook Statistics', 'smartbook' ) );

		printf( '<h2>%s</h2>', esc_html__( 'Books by Status', 'smartbook' ) );
		$this->render_table( array( __( 'Status', 'smartbook' ), __( 'Count', 'smartbook' ) ), $this->status_breakdown() );

		printf( '<h2>%s</h2>', esc_html__( 'Terms by Taxonomy', 'smartbook' ) );
		$this->render_table( array( __( 'Taxonomy', 'smartbook' ), __( 'Terms', 'smartbook' ) ), $this->taxonomy_breakdown() );

		echo '</div>';
	}

	/**
	 * Render a simple two-column, striped admin table.
	 *
	 * @param array{0: string, 1: string}         $headers Column headings.
	 * @param array<int, array{label: string, count: int}> $rows Rows to render.
	 */
	private function render_table( array $headers, array $rows ): void {
		echo '<table class="widefat striped sb-stats-table">';
		echo '<thead><tr>';
		foreach ( $headers as $header ) {
			printf( '<th>%s</th>', esc_html( $header ) );
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			printf(
				'<tr><td>%s</td><td>%s</td></tr>',
				esc_html( $row['label'] ),
				esc_html( (string) $row['count'] )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Book counts grouped by post status.
	 *
	 * @return array<int, array{label: string, count: int}>
	 */
	private function status_breakdown(): array {
		$counts = wp_count_posts( BookPostType::SLUG );

		$labels = array(
			'publish' => __( 'Published', 'smartbook' ),
			'draft'   => __( 'Draft', 'smartbook' ),
			'pending' => __( 'Pending Review', 'smartbook' ),
			'private' => __( 'Private', 'smartbook' ),
			'trash'   => __( 'Trash', 'smartbook' ),
		);

		$rows = array();

		foreach ( $labels as $status => $label ) {
			$rows[] = array(
				'label' => $label,
				'count' => (int) ( $counts->{$status} ?? 0 ),
			);
		}

		return $rows;
	}

	/**
	 * Term counts for every taxonomy the plugin registers.
	 *
	 * @return array<int, array{label: string, count: int}>
	 */
	private function taxonomy_breakdown(): array {
		$rows = array();

		foreach ( TaxonomyServiceProvider::TAXONOMIES as $taxonomy_class ) {
			$taxonomy = new $taxonomy_class();
			$count    = wp_count_terms( array( 'taxonomy' => $taxonomy->get_slug(), 'hide_empty' => false ) );

			$rows[] = array(
				'label' => $taxonomy->get_plural_label(),
				'count' => is_wp_error( $count ) ? 0 : (int) $count,
			);
		}

		return $rows;
	}
}
