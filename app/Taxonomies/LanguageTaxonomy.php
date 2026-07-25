<?php
/**
 * The "sb_language" taxonomy.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Taxonomies;

/**
 * Flat (tag-style) list of book languages. Not hierarchical: languages
 * do not nest under one another.
 */
final class LanguageTaxonomy extends AbstractTaxonomy {

	/**
	 * {@inheritDoc}
	 */
	protected function slug(): string {
		return 'sb_language';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function singular_name(): string {
		return _x( 'Language', 'taxonomy singular name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function plural_name(): string {
		return _x( 'Languages', 'taxonomy general name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function hierarchical(): bool {
		return false;
	}
}
