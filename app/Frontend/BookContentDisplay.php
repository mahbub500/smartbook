<?php
/**
 * Frontend book details panel.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Frontend;

use SmartBook\Core\Contracts\Hookable;
use SmartBook\MetaBoxes\BookFields;
use SmartBook\PostTypes\BookPostType;
use SmartBook\Taxonomies\AuthorTaxonomy;
use SmartBook\Taxonomies\CollectionTaxonomy;
use SmartBook\Taxonomies\GenreTaxonomy;
use SmartBook\Taxonomies\PublisherTaxonomy;
use SmartBook\Taxonomies\SeriesTaxonomy;
use SmartBook\Taxonomies\ShelfTaxonomy;

use function sb_format_currency;
use function sb_format_date;
use function sb_option;

/**
 * Dresses up a single book's own page (the page its QR code links to):
 * a hero (cover, byline, genre/format badges, rating, price, reading
 * progress) prepended before the post content, a bibliographic details
 * panel and any Summary/Notes appended after it, and a photo gallery
 * (the "sb_gallery" meta AddBookPage/EditBookPage's media picker
 * writes) at the very end. Also opens comments on every published book
 * for logged-in users only (force_comments_open()/require_login()) --
 * the theme's own comments_template() call still decides where on the
 * page the actual comment list/form appears, same as it does for any
 * other post type.
 *
 * Only ever shown on the book's own singular page, never in a loop/
 * archive/excerpt context (is_singular()/in_the_loop()/is_main_query()
 * guard this). Deliberately never surfaces the Borrow Management fields
 * (who a copy is lent to, borrow/return/reminder dates) or the
 * Favorite/Wishlist flags -- those are library-management bookkeeping
 * for site admins, not something to broadcast to an arbitrary visitor.
 */
final class BookContentDisplay implements Hookable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'the_content', array( $this, 'append_panel' ) );
		add_filter( 'comments_open', array( $this, 'force_comments_open' ), 10, 2 );
		add_filter( 'pre_option_comment_registration', array( $this, 'require_login_for_book_comments' ) );
	}

	/**
	 * Every published book accepts comments, regardless of its own
	 * stored "comment_status" -- necessary because that status is fixed
	 * at creation time from the post type's default, so any book created
	 * before "comments" was added to BookPostType's supports would
	 * otherwise stay stuck closed forever.
	 */
	public function force_comments_open( bool $open, int $post_id ): bool {
		if ( BookPostType::SLUG !== get_post_type( $post_id ) ) {
			return $open;
		}

		return 'publish' === get_post_status( $post_id );
	}

	/**
	 * Require a logged-in account to actually post a book comment
	 * (reading existing comments stays open to everyone), by making
	 * WordPress's own "comment_registration" option -- which
	 * comment_form() and wp-comments-post.php both already check --
	 * read as "on" specifically for a book, regardless of the sitewide
	 * Settings > Discussion value.
	 *
	 * Checked two different ways because this same option is consulted
	 * in two different contexts that don't share one signal: is_singular()
	 * identifies the book while comment_form() is rendering the page, but
	 * wp-comments-post.php (processing the actual submission) never runs
	 * the normal query/template cycle, so is_singular() is always false
	 * there -- $_POST['comment_post_ID'] is what it relies on instead.
	 *
	 * @param mixed $value The site's actual "comment_registration" option value.
	 */
	public function require_login_for_book_comments( mixed $value ): mixed {
		$post_id = 0;

		if ( isset( $_POST['comment_post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only lookup of which post this is about; wp-comments-post.php itself owns the actual nonce/capability checks for the submission.
			$post_id = absint( $_POST['comment_post_ID'] );
		} elseif ( is_singular( BookPostType::SLUG ) ) {
			$post_id = get_queried_object_id();
		}

		if ( $post_id > 0 && BookPostType::SLUG === get_post_type( $post_id ) ) {
			return '1';
		}

		return $value;
	}

	/**
	 * Wrap the post content with the hero (before) and details
	 * panel/gallery (after), on a book's own singular page, in the main
	 * loop only.
	 */
	public function append_panel( string $content ): string {
		if ( ! is_singular( BookPostType::SLUG ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();

		return $this->render_hero( $post_id ) . $content . $this->render_details_panel( $post_id ) . $this->render_gallery( $post_id );
	}

	/**
	 * Build the "at a glance" hero: cover, byline, genre/format badges,
	 * rating, price, and (when enabled) a reading-status/progress block.
	 */
	private function render_hero( int $post_id ): string {
		$html  = '<div class="sb-book-hero">';
		$html .= $this->cover( $post_id );
		$html .= '<div class="sb-book-hero__info">';

		$badges = $this->badges( $post_id );

		if ( '' !== $badges ) {
			$html .= sprintf( '<div class="sb-book-hero__badges">%s</div>', $badges );
		}

		$authors = $this->terms( $post_id, AuthorTaxonomy::SLUG );

		if ( '' !== $authors ) {
			$html .= sprintf(
				'<p class="sb-book-hero__authors">%s</p>',
				sprintf(
					/* translators: %s: comma-separated author names, already escaped. */
					esc_html__( 'by %s', 'smartbook' ),
					$authors
				)
			);
		}

		$meta = array_filter(
			array( $this->rating( $post_id ), $this->price( $post_id ) ),
			static fn ( string $part ): bool => '' !== $part
		);

		if ( array() !== $meta ) {
			$html .= sprintf( '<p class="sb-book-hero__meta">%s</p>', implode( ' &middot; ', $meta ) );
		}

		if ( sb_option( 'enable_reading_tracker', true ) ) {
			$html .= $this->reading_block( $post_id );
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Genre + format pills, already escaped, empty when the book has
	 * neither.
	 */
	private function badges( int $post_id ): string {
		$badges = array();

		foreach ( $this->term_list( $post_id, GenreTaxonomy::SLUG ) as $genre ) {
			$badges[] = sprintf( '<span class="sb-book-badge">%s</span>', esc_html( $genre ) );
		}

		$format = $this->format_label( $post_id );

		if ( '' !== $format ) {
			$badges[] = sprintf( '<span class="sb-book-badge sb-book-badge--format">%s</span>', esc_html( $format ) );
		}

		return implode( '', $badges );
	}

	/**
	 * Reading-status badge plus progress bar, already escaped. Omits the
	 * status badge when unset and the progress bar when 0%, and returns
	 * '' entirely when there is nothing to show.
	 */
	private function reading_block( int $post_id ): string {
		$status   = $this->status_label( $post_id );
		$progress = $this->progress( $post_id );

		if ( '' === $status && '' === $progress ) {
			return '';
		}

		$html = '<div class="sb-book-hero__reading">';

		if ( '' !== $status ) {
			$html .= sprintf( '<span class="sb-book-badge sb-book-badge--status">%s</span>', $status );
		}

		if ( '' !== $progress ) {
			$html .= $progress;
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Build the bibliographic details panel (shelf, publisher, series,
	 * collection, ISBNs, pages, edition, language, condition, purchase
	 * date) plus any Summary/Notes text, omitted entirely when every
	 * field is empty.
	 */
	private function render_details_panel( int $post_id ): string {
		$rows  = $this->render_row( __( 'Shelf', 'smartbook' ), $this->terms( $post_id, ShelfTaxonomy::SLUG ) );
		$rows .= $this->render_row( __( 'Publisher', 'smartbook' ), $this->terms( $post_id, PublisherTaxonomy::SLUG ) );
		$rows .= $this->render_row( __( 'Series', 'smartbook' ), $this->terms( $post_id, SeriesTaxonomy::SLUG ) );
		$rows .= $this->render_row( __( 'Collection', 'smartbook' ), $this->terms( $post_id, CollectionTaxonomy::SLUG ) );
		$rows .= $this->render_row( __( 'ISBN-10', 'smartbook' ), $this->field( $post_id, 'sb_isbn' ) );
		$rows .= $this->render_row( __( 'ISBN-13', 'smartbook' ), $this->field( $post_id, 'sb_isbn13' ) );
		$rows .= $this->render_row( __( 'Pages', 'smartbook' ), $this->field( $post_id, 'sb_pages' ) );
		$rows .= $this->render_row( __( 'Edition', 'smartbook' ), $this->field( $post_id, 'sb_edition' ) );
		$rows .= $this->render_row( __( 'Language', 'smartbook' ), $this->field( $post_id, 'sb_language' ) );
		$rows .= $this->render_row( __( 'Condition', 'smartbook' ), $this->choice_label( $post_id, 'sb_condition' ) );
		$rows .= $this->render_row( __( 'Purchase Date', 'smartbook' ), $this->date_field( $post_id, 'sb_purchase_date' ) );

		$summary = (string) get_post_meta( $post_id, 'sb_summary', true );
		$notes   = (string) get_post_meta( $post_id, 'sb_notes', true );

		if ( '' === $rows && '' === $summary && '' === $notes ) {
			return '';
		}

		$html  = '<div class="sb-book-panel">';
		$html .= sprintf( '<h2 class="sb-book-panel__title">%s</h2>', esc_html__( 'Book Details', 'smartbook' ) );

		if ( '' !== $rows ) {
			$html .= '<dl class="sb-book-panel__list">' . $rows . '</dl>';
		}

		if ( '' !== $summary ) {
			$html .= sprintf( '<h3 class="sb-book-panel__notes-title">%s</h3>', esc_html__( 'Summary', 'smartbook' ) );
			$html .= sprintf( '<p class="sb-book-panel__notes">%s</p>', esc_html( $summary ) );
		}

		if ( '' !== $notes ) {
			$html .= sprintf( '<h3 class="sb-book-panel__notes-title">%s</h3>', esc_html__( 'Notes', 'smartbook' ) );
			$html .= sprintf( '<p class="sb-book-panel__notes">%s</p>', esc_html( $notes ) );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * A grid of the book's gallery photos (the "sb_gallery" meta the Add/
	 * Edit Book media picker writes). Each thumbnail is progressively
	 * enhanced by sb-public.js' sb_initGalleryModal() into a click-to-open
	 * modal with its full-size image (data-sb-gallery-full/-alt carry
	 * what that needs); without JavaScript, the link still works as a
	 * plain "open the full image" link. Empty when the book has no
	 * gallery.
	 */
	private function render_gallery( int $post_id ): string {
		$items = '';

		foreach ( $this->gallery_ids( $post_id ) as $attachment_id ) {
			$thumb = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => 'sb-book-gallery__image' ) );

			if ( '' === $thumb ) {
				continue;
			}

			$full_url = wp_get_attachment_image_url( $attachment_id, 'full' );
			$full_url = false !== $full_url ? $full_url : '';
			$alt      = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$alt      = '' !== $alt ? (string) $alt : get_the_title( $attachment_id );

			$items .= sprintf(
				'<a class="sb-book-gallery__item" href="%1$s" target="_blank" rel="noopener noreferrer" data-sb-gallery-full="%1$s" data-sb-gallery-alt="%2$s">%3$s</a>',
				esc_url( $full_url ),
				esc_attr( $alt ),
				$thumb
			);
		}

		if ( '' === $items ) {
			return '';
		}

		return '<div class="sb-book-gallery">'
			. sprintf( '<h2 class="sb-book-gallery__title">%s</h2>', esc_html__( 'Gallery', 'smartbook' ) )
			. '<div class="sb-book-gallery__grid" data-sb-gallery>' . $items . '</div>'
			. '</div>';
	}

	/**
	 * One label/value row, omitted entirely when the value is empty.
	 *
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
	 * The book's cover image, linked-page-ready markup straight from
	 * core, or a placeholder glyph when it has none.
	 */
	private function cover( int $post_id ): string {
		if ( has_post_thumbnail( $post_id ) ) {
			return get_the_post_thumbnail( $post_id, 'medium', array( 'class' => 'sb-book-hero__cover' ) );
		}

		return '<span class="sb-book-hero__cover sb-book-hero__cover--placeholder" aria-hidden="true">&#128214;</span>';
	}

	/**
	 * Comma-separated term names for one taxonomy, already escaped.
	 */
	private function terms( int $post_id, string $taxonomy ): string {
		$names = $this->term_list( $post_id, $taxonomy );

		return array() !== $names ? esc_html( implode( ', ', $names ) ) : '';
	}

	/**
	 * Raw (unescaped) term names for one taxonomy.
	 *
	 * @return string[]
	 */
	private function term_list( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		return wp_list_pluck( $terms, 'name' );
	}

	/**
	 * A plain-text BookFields meta value, already escaped, '' when unset.
	 */
	private function field( int $post_id, string $key ): string {
		$value = (string) get_post_meta( $post_id, $key, true );

		return '' !== $value ? esc_html( $value ) : '';
	}

	/**
	 * A "Y-m-d" BookFields date meta value, formatted per the site's
	 * configured date format, already escaped, '' when unset.
	 */
	private function date_field( int $post_id, string $key ): string {
		$value = (string) get_post_meta( $post_id, $key, true );

		return '' !== $value ? esc_html( sb_format_date( $value ) ) : '';
	}

	/**
	 * A select-type BookFields value's translated option label (e.g.
	 * "sb_condition"), already escaped, '' when unset/unrecognised.
	 */
	private function choice_label( int $post_id, string $key ): string {
		$value   = (string) get_post_meta( $post_id, $key, true );
		$options = BookFields::definitions()[ $key ]['options'] ?? array();

		return ( '' !== $value && isset( $options[ $value ] ) ) ? esc_html( $options[ $value ] ) : '';
	}

	/**
	 * The "sb_format" field's translated option label, already escaped.
	 */
	private function format_label( int $post_id ): string {
		return $this->choice_label( $post_id, 'sb_format' );
	}

	/**
	 * Translated reading-status label, already escaped, reusing the same
	 * option labels the edit-screen meta box uses.
	 */
	private function status_label( int $post_id ): string {
		return $this->choice_label( $post_id, 'sb_status' );
	}

	/**
	 * A visual progress bar plus percentage, already escaped.
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

	/**
	 * The book's price formatted per the site's currency setting,
	 * already escaped, omitted when unset.
	 */
	private function price( int $post_id ): string {
		$price = (float) get_post_meta( $post_id, 'sb_price', true );

		return $price > 0 ? esc_html( sb_format_currency( $price ) ) : '';
	}

	/**
	 * Attachment ids from the "sb_gallery" meta (comma-separated,
	 * written by the Add/Edit Book media picker).
	 *
	 * @return int[]
	 */
	private function gallery_ids( int $post_id ): array {
		$raw = (string) get_post_meta( $post_id, 'sb_gallery', true );

		if ( '' === $raw ) {
			return array();
		}

		return array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
	}
}
