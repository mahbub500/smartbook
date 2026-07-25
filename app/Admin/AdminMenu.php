<?php
/**
 * The SmartBook admin menu.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin;

use SmartBook\Admin\Pages\BarcodeLabelsPage;
use SmartBook\Admin\Pages\BooksPage;
use SmartBook\Admin\Pages\DashboardPage;
use SmartBook\Admin\Pages\ImportExportPage;
use SmartBook\Admin\Pages\QrLabelsPage;
use SmartBook\Admin\Pages\SettingsPage;
use SmartBook\Admin\Pages\StatisticsPage;
use SmartBook\Core\Contracts\Hookable;
use SmartBook\PostTypes\BookPostType;
use SmartBook\Taxonomies\AuthorTaxonomy;
use SmartBook\Taxonomies\GenreTaxonomy;
use SmartBook\Taxonomies\PublisherTaxonomy;
use SmartBook\Taxonomies\ShelfTaxonomy;

use function sb_option;

/**
 * Registers the top-level "SmartBook" admin menu and its nine entries:
 * Dashboard, Books, Authors, Genres, Publishers, Shelves, Statistics,
 * Import Export, and Settings, plus two deliberately hidden label-print
 * pages, "QR Labels" and "Barcode Labels" (see register()).
 *
 * BookPostType and the four linked taxonomies are registered with
 * show_in_menu => false, so WordPress does not also auto-add its own
 * menu entries for them; every entry here is explicit, so the visible
 * menu is exactly those nine items, no more.
 */
final class AdminMenu implements Hookable {

	/**
	 * Top-level and "Dashboard" submenu slug.
	 */
	public const PARENT_SLUG = 'sb_dashboard';

	/**
	 * @param DashboardPage     $dashboard      Dashboard page renderer.
	 * @param BooksPage         $books          Books list page renderer.
	 * @param StatisticsPage    $statistics     Statistics page renderer.
	 * @param ImportExportPage  $import_export  Import/export page renderer.
	 * @param QrLabelsPage      $qr_labels      QR label print page renderer.
	 * @param BarcodeLabelsPage $barcode_labels Barcode label print page renderer.
	 * @param SettingsPage      $settings       Settings page renderer.
	 */
	public function __construct(
		private readonly DashboardPage $dashboard,
		private readonly BooksPage $books,
		private readonly StatisticsPage $statistics,
		private readonly ImportExportPage $import_export,
		private readonly QrLabelsPage $qr_labels,
		private readonly BarcodeLabelsPage $barcode_labels,
		private readonly SettingsPage $settings
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register' ) );
	}

	/**
	 * Register the top-level menu and every submenu entry.
	 */
	public function register(): void {
		add_menu_page(
			__( 'SmartBook', 'smartbook' ),
			__( 'SmartBook', 'smartbook' ),
			BookPostType::CAP_EDIT_BOOKS,
			self::PARENT_SLUG,
			array( $this->dashboard, 'render' ),
			$this->icon(),
			25
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Dashboard', 'smartbook' ),
			__( 'Dashboard', 'smartbook' ),
			BookPostType::CAP_EDIT_BOOKS,
			self::PARENT_SLUG,
			array( $this->dashboard, 'render' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Books', 'smartbook' ),
			__( 'Books', 'smartbook' ),
			BookPostType::CAP_EDIT_BOOKS,
			'sb_books',
			array( $this->books, 'render' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Authors', 'smartbook' ),
			__( 'Authors', 'smartbook' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . AuthorTaxonomy::SLUG . '&post_type=' . BookPostType::SLUG
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Genres', 'smartbook' ),
			__( 'Genres', 'smartbook' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . GenreTaxonomy::SLUG . '&post_type=' . BookPostType::SLUG
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Publishers', 'smartbook' ),
			__( 'Publishers', 'smartbook' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . PublisherTaxonomy::SLUG . '&post_type=' . BookPostType::SLUG
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Shelves', 'smartbook' ),
			__( 'Shelves', 'smartbook' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . ShelfTaxonomy::SLUG . '&post_type=' . BookPostType::SLUG
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Statistics', 'smartbook' ),
			__( 'Statistics', 'smartbook' ),
			BookPostType::CAP_EDIT_BOOKS,
			'sb_statistics',
			array( $this->statistics, 'render' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Import Export', 'smartbook' ),
			__( 'Import Export', 'smartbook' ),
			BookPostType::CAP_EDIT_BOOKS,
			'sb_import_export',
			array( $this->import_export, 'render' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Settings', 'smartbook' ),
			__( 'Settings', 'smartbook' ),
			'manage_options',
			'sb_settings',
			array( $this->settings, 'render' )
		);

		// Registered so admin.php?page=sb_qr_labels (and sb_barcode_labels
		// below) route and are capability-gated like any other page, then
		// immediately hidden from the visible menu: both are reached via
		// the books table's "Print ... Labels" bulk actions, their per-row
		// "Print Label" links, and their respective meta boxes, not via
		// direct navigation. Skipped entirely when the matching feature
		// is disabled (Settings\Settings), so the page simply doesn't
		// exist rather than existing-but-hidden.
		if ( sb_option( 'enable_qr', true ) ) {
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'QR Labels', 'smartbook' ),
				__( 'QR Labels', 'smartbook' ),
				BookPostType::CAP_EDIT_BOOKS,
				'sb_qr_labels',
				array( $this->qr_labels, 'render' )
			);

			remove_submenu_page( self::PARENT_SLUG, 'sb_qr_labels' );
		}

		if ( sb_option( 'enable_barcode', true ) ) {
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'Barcode Labels', 'smartbook' ),
				__( 'Barcode Labels', 'smartbook' ),
				BookPostType::CAP_EDIT_BOOKS,
				'sb_barcode_labels',
				array( $this->barcode_labels, 'render' )
			);

			remove_submenu_page( self::PARENT_SLUG, 'sb_barcode_labels' );
		}
	}

	/**
	 * Build a base64-encoded SVG data URI for the top-level menu icon,
	 * following the WordPress convention of a solid, single-colour glyph
	 * that the admin CSS recolours to match the current colour scheme.
	 */
	private function icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="black">'
			. '<path d="M10 3C8.35 1.9 6.02 1.5 4 1.5A1.5 1.5 0 0 0 2.5 3v12A1.5 1.5 0 0 0 4 16.5c1.86 0 3.98.36 5.55 1.34.14.09.32.14.45.14s.31-.05.45-.14C12.02 16.86 14.14 16.5 16 16.5a1.5 1.5 0 0 0 1.5-1.5V3A1.5 1.5 0 0 0 16 1.5c-2.02 0-4.35.4-6 1.5Zm-.75 12.1c-1.6-.75-3.55-1.1-5.25-1.1a.25.25 0 0 1-.25-.25V3a.25.25 0 0 1 .25-.25c1.7 0 3.65.35 5.25 1.32V15.1Zm1.5-10.03c1.6-.97 3.55-1.32 5.25-1.32.14 0 .25.11.25.25v11.75a.25.25 0 0 1-.25.25c-1.7 0-3.65.35-5.25 1.1V5.07Z"/>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
