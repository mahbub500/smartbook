<?php
/**
 * Admin service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Admin;

use SmartBook\Admin\Pages\DashboardPage;
use SmartBook\Admin\Pages\ImportExportPage;
use SmartBook\Admin\Pages\SettingsPage;
use SmartBook\Admin\Pages\StatisticsPage;
use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;
use SmartBook\Settings\Settings;

/**
 * Binds and boots the admin menu and every admin page it links to.
 */
final class AdminServiceProvider extends AbstractServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( ContainerInterface $container ): void {
		$container->singleton( DashboardPage::class, static fn (): DashboardPage => new DashboardPage() );
		$container->singleton( StatisticsPage::class, static fn (): StatisticsPage => new StatisticsPage() );
		$container->singleton( ImportExportPage::class, static fn (): ImportExportPage => new ImportExportPage() );

		$container->singleton(
			SettingsPage::class,
			static fn ( ContainerInterface $container ): SettingsPage => new SettingsPage( $container->make( Settings::class ) )
		);

		$container->singleton(
			AdminMenu::class,
			static fn ( ContainerInterface $container ): AdminMenu => new AdminMenu(
				$container->make( DashboardPage::class ),
				$container->make( StatisticsPage::class ),
				$container->make( ImportExportPage::class ),
				$container->make( SettingsPage::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( ContainerInterface $container ): void {
		$container->make( AdminMenu::class )->register_hooks();
		$container->make( ImportExportPage::class )->register_hooks();
		$container->make( SettingsPage::class )->register_hooks();
	}
}
