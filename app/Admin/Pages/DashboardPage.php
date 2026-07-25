<?php
/**
 * The SmartBook admin dashboard page.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin\Pages;

use SmartBook\PostTypes\BookPostType;

/**
 * Renders a snapshot of the library (status counts) plus quick links to
 * the most common actions.
 */
final class DashboardPage {

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( BookPostType::CAP_EDIT_BOOKS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smartbook' ) );
		}

		$counts = wp_count_posts( BookPostType::SLUG );

		echo '<div class="wrap sb-admin-page">';
		printf( '<h1>%s</h1>', esc_html__( 'SmartBook Dashboard', 'smartbook' ) );

		echo '<div class="sb-dashboard__cards">';
		$this->render_card( __( 'Published Books', 'smartbook' ), (int) ( $counts->publish ?? 0 ) );
		$this->render_card( __( 'Drafts', 'smartbook' ), (int) ( $counts->draft ?? 0 ) );
		$this->render_card( __( 'Pending Review', 'smartbook' ), (int) ( $counts->pending ?? 0 ) );
		$this->render_card( __( 'Private', 'smartbook' ), (int) ( $counts->private ?? 0 ) );
		echo '</div>';

		printf( '<h2>%s</h2>', esc_html__( 'Quick Links', 'smartbook' ) );
		echo '<ul class="sb-dashboard__links">';
		$this->render_link( __( 'Add New Book', 'smartbook' ), admin_url( 'post-new.php?post_type=' . BookPostType::SLUG ) );
		$this->render_link( __( 'View All Books', 'smartbook' ), admin_url( 'edit.php?post_type=' . BookPostType::SLUG ) );
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
	 * Render a single quick-link list item.
	 */
	private function render_link( string $label, string $url ): void {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( $url ),
			esc_html( $label )
		);
	}
}
