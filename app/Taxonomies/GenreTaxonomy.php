<?php
/**
 * The "sb_genre" taxonomy.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hierarchical (category-style) genre classification, e.g.
 * Fiction > Fantasy > Epic Fantasy.
 */
final class GenreTaxonomy extends AbstractTaxonomy {

	/**
	 * Taxonomy slug.
	 */
	public const SLUG = 'sb_genre';

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
		return _x( 'Genre', 'taxonomy singular name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function plural_name(): string {
		return _x( 'Genres', 'taxonomy general name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function hierarchical(): bool {
		return true;
	}
}
