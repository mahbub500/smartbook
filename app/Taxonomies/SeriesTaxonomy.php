<?php
/**
 * The "sb_series" taxonomy.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Taxonomies;

/**
 * Flat (tag-style) list of book series. Not hierarchical: a series is a
 * named grouping, not a nested classification.
 */
final class SeriesTaxonomy extends AbstractTaxonomy {

	/**
	 * {@inheritDoc}
	 */
	protected function slug(): string {
		return 'sb_series';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function singular_name(): string {
		return _x( 'Series', 'taxonomy singular name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function plural_name(): string {
		return _x( 'Series', 'taxonomy general name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function hierarchical(): bool {
		return false;
	}
}
