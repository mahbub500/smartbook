<?php
/**
 * The "sb_collection" taxonomy.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Taxonomies;

/**
 * Hierarchical (category-style) curated collection classification, e.g.
 * "Rare Books" > "First Editions".
 */
final class CollectionTaxonomy extends AbstractTaxonomy {

	/**
	 * {@inheritDoc}
	 */
	protected function slug(): string {
		return 'sb_collection';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function singular_name(): string {
		return _x( 'Collection', 'taxonomy singular name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function plural_name(): string {
		return _x( 'Collections', 'taxonomy general name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function hierarchical(): bool {
		return true;
	}
}
