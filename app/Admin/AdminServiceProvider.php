<?php
/**
 * Admin service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Admin\Pages\BarcodeLabelsPage;
use SmartBook\Admin\Pages\BooksPage;
use SmartBook\Admin\Pages\DashboardPage;
use SmartBook\Admin\Pages\ImportExportPage;
use SmartBook\Admin\Pages\QrLabelsPage;
use SmartBook\Admin\Pages\SettingsPage;
use SmartBook\Admin\Pages\StatisticsPage;
use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;
use SmartBook\Services\BarcodeManager;
use SmartBook\Services\BookStats;
use SmartBook\Services\QrCodeManager;
use SmartBook\Settings\Settings;

/**
 * Binds and boots the admin menu and every admin page it links to.
 */
final class AdminServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton( BookStats::class, static fn (): BookStats => new BookStats() );

		$container->singleton(
			DashboardPage::class,
			static fn ( ContainerInterface $container ): DashboardPage => new DashboardPage( $container->make( BookStats::class ) )
		);

		$container->singleton(
			BooksPage::class,
			static fn ( ContainerInterface $container ): BooksPage => new BooksPage( $container->make( BarcodeManager::class ) )
		);

		$container->singleton( StatisticsPage::class, static fn (): StatisticsPage => new StatisticsPage() );
		$container->singleton( ImportExportPage::class, static fn (): ImportExportPage => new ImportExportPage() );

		$container->singleton(
			SettingsPage::class,
			static fn ( ContainerInterface $container ): SettingsPage => new SettingsPage( $container->make( Settings::class ) )
		);

		$container->singleton(
			QrLabelsPage::class,
			static fn ( ContainerInterface $container ): QrLabelsPage => new QrLabelsPage( $container->make( QrCodeManager::class ) )
		);

		$container->singleton(
			BarcodeLabelsPage::class,
			static fn ( ContainerInterface $container ): BarcodeLabelsPage => new BarcodeLabelsPage( $container->make( BarcodeManager::class ) )
		);

		$container->singleton(
			AdminMenu::class,
			static fn ( ContainerInterface $container ): AdminMenu => new AdminMenu(
				$container->make( DashboardPage::class ),
				$container->make( BooksPage::class ),
				$container->make( StatisticsPage::class ),
				$container->make( ImportExportPage::class ),
				$container->make( QrLabelsPage::class ),
				$container->make( BarcodeLabelsPage::class ),
				$container->make( SettingsPage::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function boot( ContainerInterface $container ): void {
		$container->make( AdminMenu::class )->register_hooks();
		$container->make( DashboardPage::class )->register_hooks();
		$container->make( ImportExportPage::class )->register_hooks();
		$container->make( SettingsPage::class )->register_hooks();
	}
}
