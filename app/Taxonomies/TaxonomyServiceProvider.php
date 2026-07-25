<?php
/**
 * Taxonomy service provider.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Core\AbstractServiceProvider;
use SmartBook\Core\Contracts\ContainerInterface;

/**
 * Binds and boots every taxonomy the plugin registers.
 */
final class TaxonomyServiceProvider extends AbstractServiceProvider {

	/**
	 * Every taxonomy class this plugin registers.
	 *
	 * Also consumed directly by Core\Activator, which must register
	 * taxonomies synchronously (not just via the "init" hook) before
	 * flushing rewrite rules on activation.
	 *
	 * @var class-string<AbstractTaxonomy>[]
	 */
	public const TAXONOMIES = array(
		AuthorTaxonomy::class,
		GenreTaxonomy::class,
		PublisherTaxonomy::class,
		LanguageTaxonomy::class,
		SeriesTaxonomy::class,
		ShelfTaxonomy::class,
		CollectionTaxonomy::class,
	);

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function register( ContainerInterface $container ): void {
		foreach ( self::TAXONOMIES as $taxonomy_class ) {
			$container->singleton( $taxonomy_class, static fn (): AbstractTaxonomy => new $taxonomy_class() );
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $container Application service container.
	 */
	public function boot( ContainerInterface $container ): void {
		foreach ( self::TAXONOMIES as $taxonomy_class ) {
			$container->make( $taxonomy_class )->register_hooks();
		}
	}
}
