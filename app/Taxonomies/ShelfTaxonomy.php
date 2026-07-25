<?php
/**
 * The "sb_shelf" taxonomy.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flat (tag-style) list of physical or virtual shelf locations. Not
 * hierarchical: shelves are treated as simple location codes.
 */
final class ShelfTaxonomy extends AbstractTaxonomy {

	/**
	 * Taxonomy slug.
	 */
	public const SLUG = 'sb_shelf';

	/**
	 * {@inheritDoc}
	 */
	protected function slug(): string {
		return self::SLUG;
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
