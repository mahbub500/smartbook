<?php
/**
 * Book catalog statistics.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Services;

use DateTimeImmutable;
use SmartBook\PostTypes\BookPostType;
use SmartBook\Taxonomies\AuthorTaxonomy;
use SmartBook\Taxonomies\GenreTaxonomy;
use WP_Post;

/**
 * Aggregates counts and chart-ready datasets from the book catalog, for
 * the dashboard cards/charts and (potentially) anywhere else that needs
 * the same figures. The underlying post collection is fetched once per
 * request and reused by every method, since a dashboard view typically
 * calls several of them.
 */
final class BookStats {

	/**
	 * Every non-trash book, fetched once and reused.
	 *
	 * @var WP_Post[]|null
	 */
	private ?array $posts = null;

	/**
	 * Total number of books in the catalog (any non-trash status).
	 */
	public function total(): int {
		return count( $this->posts() );
	}

	/**
	 * Number of books whose "sb_status" meta equals the given value.
	 */
	public function count_by_status( string $status ): int {
		return count(
			array_filter(
				$this->posts(),
				static fn ( WP_Post $post ): bool => $status === (string) get_post_meta( $post->ID, 'sb_status', true )
			)
		);
	}

	/**
	 * Number of books whose boolean meta flag (e.g. "sb_favorite",
	 * "sb_wishlist", "sb_borrowed") is set.
	 */
	public function count_by_flag( string $meta_key ): int {
		return count(
			array_filter(
				$this->posts(),
				static fn ( WP_Post $post ): bool => '1' === (string) get_post_meta( $post->ID, $meta_key, true )
			)
		);
	}

	/**
	 * Book counts grouped by the year of their "sb_purchase_date" meta,
	 * ascending. Books without a purchase date are not counted, since
	 * there is nothing to chart them by.
	 *
	 * @return array<string, int> Year => count.
	 */
	public function books_per_year(): array {
		$years = array();

		foreach ( $this->posts() as $post ) {
			$date = (string) get_post_meta( $post->ID, 'sb_purchase_date', true );

			if ( '' === $date ) {
				continue;
			}

			$year           = substr( $date, 0, 4 );
			$years[ $year ] = ( $years[ $year ] ?? 0 ) + 1;
		}

		ksort( $years );

		return $years;
	}

	/**
	 * The ten most-used genre terms and their book counts, descending.
	 *
	 * @return array<string, int> Genre name => count.
	 */
	public function books_per_genre(): array {
		return $this->books_per_taxonomy( GenreTaxonomy::SLUG );
	}

	/**
	 * The ten most-used author terms and their book counts, descending.
	 *
	 * @return array<string, int> Author name => count.
	 */
	public function books_per_author(): array {
		return $this->books_per_taxonomy( AuthorTaxonomy::SLUG );
	}

	/**
	 * Books that entered "reading" or "read" status, bucketed by the
	 * month they were added to the catalog, for the last 12 months.
	 *
	 * @return array<string, int> "Y-m" => count, in chronological order.
	 */
	public function monthly_reading(): array {
		$months = array();
		$cursor = new DateTimeImmutable( 'first day of this month' );

		for ( $i = 11; $i >= 0; $i-- ) {
			$months[ $cursor->modify( "-{$i} months" )->format( 'Y-m' ) ] = 0;
		}

		foreach ( $this->posts() as $post ) {
			$status = (string) get_post_meta( $post->ID, 'sb_status', true );

			if ( ! in_array( $status, array( 'reading', 'read' ), true ) ) {
				continue;
			}

			$month = substr( $post->post_date, 0, 7 );

			if ( isset( $months[ $month ] ) ) {
				++$months[ $month ];
			}
		}

		return $months;
	}

	/**
	 * Term name => book count for a taxonomy, top ten by count.
	 *
	 * @return array<string, int>
	 */
	private function books_per_taxonomy( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 10,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$counts = array();

		foreach ( $terms as $term ) {
			$counts[ $term->name ] = (int) $term->count;
		}

		return $counts;
	}

	/**
	 * Every non-trash book, fetched once per request and cached.
	 *
	 * @return WP_Post[]
	 */
	private function posts(): array {
		if ( null === $this->posts ) {
			$this->posts = get_posts(
				array(
					'post_type'      => BookPostType::SLUG,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => -1,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
		}

		return $this->posts;
	}
}
