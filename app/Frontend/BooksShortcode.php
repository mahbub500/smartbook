<?php
/**
 * The "[smartbook_books]" shortcode.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Frontend;

use SmartBook\Core\Contracts\Hookable;
use SmartBook\MetaBoxes\BookFields;
use SmartBook\PostTypes\BookPostType;
use SmartBook\Taxonomies\AuthorTaxonomy;
use SmartBook\Taxonomies\GenreTaxonomy;
use WP_Post;

use function sb_format_currency;
use function sb_option;

/**
 * Renders every published book as a grid of cards: cover, title (linked
 * to the book's own page, where Frontend\BookContentDisplay appends its
 * details panel), author/genre, rating, reading status, and price.
 * Core\Activator publishes a page containing this shortcode on
 * activation, so a site has a working "All Books" listing immediately.
 */
final class BooksShortcode implements Hookable {

	/**
	 * Shortcode tag.
	 */
	public const TAG = 'smartbook_books';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback. Every book, published, oldest excluded (Trash,
	 * Draft, Pending, Private), title order.
	 */
	public function render(): string {
		$books = get_posts(
			array(
				'post_type'      => BookPostType::SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( array() === $books ) {
			return sprintf( '<p class="sb-books-list__empty">%s</p>', esc_html__( 'No books found.', 'smartbook' ) );
		}

		$items = '';

		foreach ( $books as $book ) {
			$items .= $this->render_book( $book );
		}

		return '<div class="sb-books-list">' . $items . '</div>';
	}

	/**
	 * Render a single book card.
	 */
	private function render_book( WP_Post $book ): string {
		$html  = '<div class="sb-book">';
		$html .= $this->cover( $book );
		$html .= sprintf(
			'<a class="sb-book__title" href="%s">%s</a>',
			esc_url( get_permalink( $book ) ),
			esc_html( get_the_title( $book ) )
		);

		$meta = $this->meta_parts( $book );

		if ( array() !== $meta ) {
			$html .= sprintf( '<div class="sb-book__meta">%s</div>', implode( ' &middot; ', $meta ) );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * The book's cover image, linked-page-ready markup straight from
	 * core, or an empty string when it has none.
	 */
	private function cover( WP_Post $book ): string {
		if ( ! has_post_thumbnail( $book ) ) {
			return '';
		}

		return get_the_post_thumbnail( $book, 'medium', array( 'class' => 'sb-book__cover' ) );
	}

	/**
	 * Already-escaped meta fragments for one book, in display order,
	 * skipping any that are empty.
	 *
	 * @return string[]
	 */
	private function meta_parts( WP_Post $book ): array {
		$parts = array_filter(
			array(
				$this->terms( $book->ID, AuthorTaxonomy::SLUG ),
				$this->terms( $book->ID, GenreTaxonomy::SLUG ),
				$this->rating( $book->ID ),
				sb_option( 'enable_reading_tracker', true ) ? $this->status_label( $book->ID ) : '',
				$this->price( $book->ID ),
			),
			static fn ( string $part ): bool => '' !== $part
		);

		return array_values( $parts );
	}

	/**
	 * Comma-separated term names for one taxonomy, already escaped.
	 */
	private function terms( int $post_id, string $taxonomy ): string {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		return esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
	}

	/**
	 * A star rating, already escaped, omitted when unset.
	 */
	private function rating( int $post_id ): string {
		$rating = max( 0, min( 5, (int) get_post_meta( $post_id, 'sb_rating', true ) ) );

		if ( 0 === $rating ) {
			return '';
		}

		return esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) );
	}

	/**
	 * Translated reading-status label, already escaped, reusing the same
	 * option labels the edit-screen meta box uses.
	 */
	private function status_label( int $post_id ): string {
		$status  = (string) get_post_meta( $post_id, 'sb_status', true );
		$options = BookFields::definitions()['sb_status']['options'] ?? array();

		return ( '' !== $status && isset( $options[ $status ] ) ) ? esc_html( $options[ $status ] ) : '';
	}

	/**
	 * The book's price formatted per the site's currency setting,
	 * already escaped, omitted when unset.
	 */
	private function price( int $post_id ): string {
		$price = (float) get_post_meta( $post_id, 'sb_price', true );

		return $price > 0 ? esc_html( sb_format_currency( $price ) ) : '';
	}
}
