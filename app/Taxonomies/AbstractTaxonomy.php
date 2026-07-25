<?php
/**
 * Base class for book taxonomies.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Taxonomies;

use SmartBook\Core\Contracts\Hookable;
use SmartBook\PostTypes\BookPostType;

/**
 * Shared registration logic for every taxonomy this plugin defines.
 *
 * Concrete subclasses only need to describe themselves (slug, labels,
 * hierarchy); the argument set passed to register_taxonomy() -- REST
 * exposure, rewrite rules, admin UI behaviour -- is built once here so
 * seven near-identical taxonomies don't repeat ~40 lines of boilerplate
 * each.
 */
abstract class AbstractTaxonomy implements Hookable {

	/**
	 * Taxonomy slug, e.g. "sb_genre".
	 */
	abstract protected function slug(): string;

	/**
	 * Translated singular label, e.g. __( 'Genre', 'smartbook' ).
	 */
	abstract protected function singular_name(): string;

	/**
	 * Translated plural label, e.g. __( 'Genres', 'smartbook' ).
	 */
	abstract protected function plural_name(): string;

	/**
	 * Whether terms in this taxonomy can have a parent (category-style)
	 * or are flat (tag-style).
	 */
	abstract protected function hierarchical(): bool;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the taxonomy with WordPress.
	 *
	 * Called both from the "init" hook (register_hooks()) and directly
	 * and synchronously from Core\Activator, since activation runs after
	 * "init" has already fired for that request and flush_rewrite_rules()
	 * needs the rewrite rules to exist beforehand.
	 */
	public function register(): void {
		register_taxonomy( $this->slug(), $this->object_types(), $this->args() );
	}

	/**
	 * Post types this taxonomy applies to. Defaults to the book post
	 * type; override to attach a taxonomy elsewhere too.
	 *
	 * @return string[]
	 */
	protected function object_types(): array {
		return array( BookPostType::SLUG );
	}

	/**
	 * Public accessor for this taxonomy's slug, for modules (e.g. the
	 * admin menu, statistics page) that need to reference it without
	 * duplicating the literal string.
	 */
	public function get_slug(): string {
		return $this->slug();
	}

	/**
	 * Public accessor for this taxonomy's translated plural label.
	 */
	public function get_plural_label(): string {
		return $this->plural_name();
	}

	/**
	 * REST base, kept identical to the taxonomy slug so every taxonomy's
	 * REST route stays under the "sb_" prefix.
	 */
	protected function rest_base(): string {
		return $this->slug();
	}

	/**
	 * Public, human-readable permalink slug, derived from the taxonomy
	 * slug by dropping the internal "sb_" prefix.
	 */
	protected function rewrite_slug(): string {
		return str_replace( 'sb_', '', $this->slug() );
	}

	/**
	 * Full argument set passed to register_taxonomy().
	 *
	 * @return array<string, mixed>
	 */
	private function args(): array {
		$hierarchical = $this->hierarchical();

		return array(
			'labels'             => $this->labels(),
			'public'             => true,
			'publicly_queryable' => true,
			'hierarchical'       => $hierarchical,
			'show_ui'            => true,
			// Term management is exposed to nav via explicit submenu
			// entries in Admin\AdminMenu (for the taxonomies that get
			// one), not WordPress's automatic per-taxonomy menu item;
			// this taxonomy's meta box on the book edit screen still
			// works regardless, since that only depends on show_ui.
			'show_in_menu'       => false,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_in_quick_edit' => true,
			'show_tagcloud'      => ! $hierarchical,
			'show_in_rest'       => true,
			'rest_base'          => $this->rest_base(),
			'query_var'          => true,
			'rewrite'            => array(
				'slug'         => $this->rewrite_slug(),
				'with_front'   => false,
				'hierarchical' => $hierarchical,
			),
		);
	}

	/**
	 * Full label set for the taxonomy.
	 *
	 * @return array<string, string>
	 */
	private function labels(): array {
		$singular = $this->singular_name();
		$plural   = $this->plural_name();

		$labels = array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			/* translators: %s: taxonomy plural label. */
			'all_items'     => sprintf( __( 'All %s', 'smartbook' ), $plural ),
			/* translators: %s: taxonomy singular label. */
			'edit_item'     => sprintf( __( 'Edit %s', 'smartbook' ), $singular ),
			/* translators: %s: taxonomy singular label. */
			'view_item'     => sprintf( __( 'View %s', 'smartbook' ), $singular ),
			/* translators: %s: taxonomy singular label. */
			'update_item'   => sprintf( __( 'Update %s', 'smartbook' ), $singular ),
			/* translators: %s: taxonomy singular label. */
			'add_new_item'  => sprintf( __( 'Add New %s', 'smartbook' ), $singular ),
			/* translators: %s: taxonomy singular label. */
			'new_item_name' => sprintf( __( 'New %s Name', 'smartbook' ), $singular ),
			/* translators: %s: taxonomy plural label. */
			'search_items'  => sprintf( __( 'Search %s', 'smartbook' ), $plural ),
			/* translators: %s: taxonomy plural label. */
			'not_found'     => sprintf( __( 'No %s found.', 'smartbook' ), $plural ),
			/* translators: %s: taxonomy plural label. */
			'no_terms'      => sprintf( __( 'No %s', 'smartbook' ), $plural ),
			/* translators: %s: taxonomy plural label. */
			'items_list_navigation' => sprintf( __( '%s list navigation', 'smartbook' ), $plural ),
			/* translators: %s: taxonomy plural label. */
			'items_list'    => sprintf( __( '%s list', 'smartbook' ), $plural ),
			/* translators: %s: taxonomy plural label. */
			'back_to_items' => sprintf( __( '&larr; Go to %s', 'smartbook' ), $plural ),
		);

		if ( $this->hierarchical() ) {
			/* translators: %s: taxonomy singular label. */
			$labels['parent_item']       = sprintf( __( 'Parent %s', 'smartbook' ), $singular );
			/* translators: %s: taxonomy singular label. */
			$labels['parent_item_colon'] = sprintf( __( 'Parent %s:', 'smartbook' ), $singular );
			/* translators: %s: taxonomy singular label. */
			$labels['filter_by_item']    = sprintf( __( 'Filter by %s', 'smartbook' ), $singular );

			return $labels;
		}

		/* translators: %s: taxonomy plural label. */
		$labels['popular_items'] = sprintf( __( 'Popular %s', 'smartbook' ), $plural );
		/* translators: %s: taxonomy plural label. */
		$labels['separate_items_with_commas'] = sprintf( __( 'Separate %s with commas', 'smartbook' ), $plural );
		/* translators: %s: taxonomy plural label. */
		$labels['add_or_remove_items'] = sprintf( __( 'Add or remove %s', 'smartbook' ), $plural );
		/* translators: %s: taxonomy plural label. */
		$labels['choose_from_most_used'] = sprintf( __( 'Choose from the most used %s', 'smartbook' ), $plural );
		$labels['most_used']             = __( 'Most Used', 'smartbook' );

		return $labels;
	}
}
