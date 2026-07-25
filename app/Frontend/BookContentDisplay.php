<?php
/**
 * Frontend book details panel.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SmartBook\Core\Contracts\Hookable;
use SmartBook\MetaBoxes\BookFields;
use SmartBook\PostTypes\BookPostType;
use SmartBook\Taxonomies\ShelfTaxonomy;

/**
 * Appends a "Book Details" panel (shelf, reading status, progress,
 * rating, notes) after the post content on a single book's own page —
 * the page a book's QR code links to.
 */
final class BookContentDisplay implements Hookable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'the_content', array( $this, 'append_panel' ) );
	}

	/**
	 * Append the details panel to the post content on a book's own
	 * singular page, in the main loop only.
	 *
	 * @param string $content Original post content.
	 */
	public function append_panel( string $content ): string {
		if ( ! is_singular( BookPostType::SLUG ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		return $content . $this->render_panel( get_the_ID() );
	}

	/**
	 * Build the panel markup for one book.
	 *
	 * @param int $post_id Book post ID.
	 */
	private function render_panel( int $post_id ): string {
		$rows  = $this->render_row( __( 'Shelf', 'smartbook' ), $this->shelf( $post_id ) );
		$rows .= $this->render_row( __( 'Reading Status', 'smartbook' ), $this->status_label( $post_id ) );
		$rows .= $this->render_row( __( 'Progress', 'smartbook' ), $this->progress( $post_id ) );
		$rows .= $this->render_row( __( 'Rating', 'smartbook' ), $this->rating( $post_id ) );

		$html  = '<div class="sb-book-panel">';
		$html .= sprintf( '<h2 class="sb-book-panel__title">%s</h2>', esc_html__( 'Book Details', 'smartbook' ) );
		$html .= '<dl class="sb-book-panel__list">' . $rows . '</dl>';

		$notes = (string) get_post_meta( $post_id, 'sb_notes', true );

		if ( '' !== $notes ) {
			$html .= sprintf( '<h3 class="sb-book-panel__notes-title">%s</h3>', esc_html__( 'Notes', 'smartbook' ) );
			$html .= sprintf( '<p class="sb-book-panel__notes">%s</p>', esc_html( $notes ) );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * One label/value row, omitted entirely when the value is empty.
	 *
	 * @param string $label      Row label text.
	 * @param string $value_html Already-escaped value markup.
	 */
	private function render_row( string $label, string $value_html ): string {
		if ( '' === $value_html ) {
			return '';
		}

		return sprintf(
			'<div class="sb-book-panel__row"><dt>%s</dt><dd>%s</dd></div>',
			esc_html( $label ),
			$value_html
		);
	}

	/**
	 * Comma-separated shelf term names, already escaped.
	 *
	 * @param int $post_id Book post ID.
	 */
	private function shelf( int $post_id ): string {
		$terms = get_the_terms( $post_id, ShelfTaxonomy::SLUG );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		return esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
	}

	/**
	 * Translated reading-status label, already escaped, reusing the same
	 * option labels the edit-screen meta box uses.
	 *
	 * @param int $post_id Book post ID.
	 */
	private function status_label( int $post_id ): string {
		$status  = (string) get_post_meta( $post_id, 'sb_status', true );
		$options = BookFields::definitions()['sb_status']['options'] ?? array();

		return ( '' !== $status && isset( $options[ $status ] ) ) ? esc_html( $options[ $status ] ) : '';
	}

	/**
	 * A visual progress bar plus percentage, already escaped.
	 *
	 * @param int $post_id Book post ID.
	 */
	private function progress( int $post_id ): string {
		$progress = max( 0, min( 100, (int) get_post_meta( $post_id, 'sb_progress', true ) ) );

		if ( 0 === $progress ) {
			return '';
		}

		return sprintf(
			'<div class="sb-book-panel__progress"><div class="sb-book-panel__progress-track"><div class="sb-book-panel__progress-fill" style="width:%1$d%%"></div></div><span>%1$d%%</span></div>',
			$progress
		);
	}

	/**
	 * A star rating, already escaped.
	 *
	 * @param int $post_id Book post ID.
	 */
	private function rating( int $post_id ): string {
		$rating = max( 0, min( 5, (int) get_post_meta( $post_id, 'sb_rating', true ) ) );

		if ( 0 === $rating ) {
			return '';
		}

		return sprintf(
			'<span class="sb-book-panel__rating" aria-label="%1$s">%2$s</span>',
			esc_attr(
				sprintf(
					/* translators: %d: rating out of 5. */
					__( '%d out of 5', 'smartbook' ),
					$rating
				)
			),
			esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) )
		);
	}
}
