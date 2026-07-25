<?php
/**
 * The "sb_publisher" taxonomy.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hierarchical (category-style) publisher classification, so imprints
 * can be registered as children of their parent publishing house.
 */
final class PublisherTaxonomy extends AbstractTaxonomy {

	/**
	 * Taxonomy slug.
	 */
	public const SLUG = 'sb_publisher';

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
		return _x( 'Publisher', 'taxonomy singular name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function plural_name(): string {
		return _x( 'Publishers', 'taxonomy general name', 'smartbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function hierarchical(): bool {
		return true;
	}
}
