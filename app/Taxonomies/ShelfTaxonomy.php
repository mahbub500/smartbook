<?php
/**
 * The "sb_shelf" taxonomy.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Taxonomies;

/**
 * Flat (tag-style) list of physical or virtual shelf locations. Not
 * hierarchical: shelves are treated as simple location codes.
 */
final class ShelfTaxonomy extends AbstractTaxonomy {

	/**
	 * {@inheritDoc}
	 */
	protected function slug(): string {
		return 'sb_shelf';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function singular_name(): string {
		return _x( 'Shelf', 'taxonomy singular name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function plural_name(): string {
		return _x( 'Shelves', 'taxonomy general name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function hierarchical(): bool {
		return false;
	}
}
