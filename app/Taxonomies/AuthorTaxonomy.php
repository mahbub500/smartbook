<?php
/**
 * The "sb_author" taxonomy.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Taxonomies;

/**
 * Flat (tag-style) list of book authors. Not hierarchical: an author
 * does not have a "parent" author.
 */
final class AuthorTaxonomy extends AbstractTaxonomy {

	/**
	 * Taxonomy slug.
	 */
	public const SLUG = 'sb_author';

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
		return _x( 'Author', 'taxonomy singular name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function plural_name(): string {
		return _x( 'Authors', 'taxonomy general name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function hierarchical(): bool {
		return false;
	}
}
