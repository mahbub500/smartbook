<?php
/**
 * The "Book Details" meta box.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\MetaBoxes;

use DateTimeImmutable;
use SmartBook\Core\Contracts\Hookable;
use SmartBook\PostTypes\BookPostType;
use WP_Post;

/**
 * Renders and persists the "Book Details" meta box on the "sb_book"
 * edit screen: sixteen sb_-prefixed meta fields covering identification,
 * condition/value, reading progress, and free-text notes.
 *
 * Every write goes through a nonce check plus a capability check
 * (can_save()) and per-field sanitization (sanitize_value()); every
 * read goes through esc_attr()/esc_html()/esc_textarea() at the point
 * of output (render_field()).
 */
final class BookDetailsMetaBox implements Hookable {

	/**
	 * Meta box DOM id.
	 */
	private const BOX_ID = 'sb_book_details';

	/**
	 * Nonce action name.
	 */
	private const NONCE_ACTION = 'sb_book_details_save';

	/**
	 * Nonce hidden field name.
	 */
	private const NONCE_NAME = 'sb_book_details_nonce';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post_' . BookPostType::SLUG, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register the meta box on the book edit screen.
	 */
	public function add(): void {
		add_meta_box(
			self::BOX_ID,
			__( 'Book Details', 'smartbook' ),
			array( $this, 'render' ),
			BookPostType::SLUG,
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box, grouped into sections for readability.
	 *
	 * @param WP_Post $post Post currently being edited.
	 */
	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$fields = $this->fields();

		echo '<div class="sb-meta-box">';

		foreach ( $this->sections() as $section ) {
			printf( '<h3 class="sb-meta-box__section-title">%s</h3>', esc_html( $section['title'] ) );
			echo '<div class="sb-meta-box__grid">';

			foreach ( $section['fields'] as $key ) {
				$this->render_field( $post, $key, $fields[ $key ] );
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Persist the submitted meta box values.
	 *
	 * @param int     $post_id Post ID being saved.
	 * @param WP_Post $post    Post object being saved.
	 */
	public function save( int $post_id, WP_Post $post ): void {
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}

		foreach ( $this->fields() as $key => $field ) {
			$this->save_field( $post_id, $key, $field );
		}
	}

	/**
	 * Whether the current request is allowed to persist meta box values:
	 * a valid nonce, not an autosave, not a revision, and the current
	 * user holds the "edit_post" meta capability for this book (which,
	 * thanks to BookPostType's map_meta_cap, resolves to the custom
	 * "edit_sb_book"/"edit_others_sb_books" capabilities).
	 *
	 * @param int $post_id Post ID being saved.
	 */
	private function can_save( int $post_id ): bool {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize and persist a single field.
	 *
	 * @param int                  $post_id Post ID being saved.
	 * @param string               $key     Meta key, e.g. "sb_isbn".
	 * @param array<string, mixed> $field   Field definition from fields().
	 */
	private function save_field( int $post_id, string $key, array $field ): void {
		// Checkboxes send no value at all when unchecked, unlike every
		// other input type, so they need their own presence check.
		if ( 'checkbox' === $field['type'] ) {
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? '1' : '' );
			return;
		}

		if ( ! isset( $_POST[ $key ] ) ) {
			return;
		}

		$raw = wp_unslash( $_POST[ $key ] );

		update_post_meta( $post_id, $key, $this->sanitize_value( $raw, $field ) );
	}

	/**
	 * Sanitize a raw submitted value according to its field definition.
	 *
	 * @param mixed                $raw   Raw, unslashed value from $_POST.
	 * @param array<string, mixed> $field Field definition from fields().
	 */
	private function sanitize_value( mixed $raw, array $field ): string|int|float {
		return match ( $field['type'] ) {
			'textarea' => sanitize_textarea_field( (string) $raw ),
			'select'   => $this->sanitize_choice( (string) $raw, $field['options'] ?? array() ),
			'number'   => $this->sanitize_number( $raw, $field ),
			'date'     => $this->sanitize_date( (string) $raw ),
			default    => sanitize_text_field( (string) $raw ),
		};
	}

	/**
	 * Keep only a value that matches one of a select field's known
	 * option keys; anything else is discarded.
	 *
	 * @param array<string, string> $options Allowed option value => label pairs.
	 */
	private function sanitize_choice( string $value, array $options ): string {
		return array_key_exists( $value, $options ) ? $value : '';
	}

	/**
	 * Coerce and clamp a numeric field to its declared type and bounds.
	 *
	 * @param mixed                $raw   Raw, unslashed value from $_POST.
	 * @param array<string, mixed> $field Field definition, may contain "numeric_type", "min", "max".
	 */
	private function sanitize_number( mixed $raw, array $field ): int|float {
		$value = 'float' === ( $field['numeric_type'] ?? 'int' ) ? (float) $raw : (int) $raw;

		if ( isset( $field['min'] ) ) {
			$value = max( $field['min'], $value );
		}

		if ( isset( $field['max'] ) ) {
			$value = min( $field['max'], $value );
		}

		return $value;
	}

	/**
	 * Keep only a strict "Y-m-d" date string; anything else is discarded
	 * rather than stored malformed.
	 */
	private function sanitize_date( string $raw ): string {
		$raw = sanitize_text_field( $raw );

		if ( '' === $raw ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( 'Y-m-d', $raw );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $raw ) {
			return '';
		}

		return $raw;
	}

	/**
	 * Render a single field's label plus its typed input control, escaping
	 * every dynamic value at the point of output.
	 *
	 * @param WP_Post              $post  Post currently being edited.
	 * @param string               $key   Meta key, e.g. "sb_isbn".
	 * @param array<string, mixed> $field Field definition from fields().
	 */
	private function render_field( WP_Post $post, string $key, array $field ): void {
		$value = get_post_meta( $post->ID, $key, true );
		$id    = esc_attr( $key );

		if ( 'checkbox' === $field['type'] ) {
			printf(
				'<div class="sb-field-group sb-field-group--checkbox"><label for="%1$s"><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s /> %3$s</label></div>',
				esc_attr( $id ),
				checked( '1', $value, false ),
				esc_html( $field['label'] )
			);

			return;
		}

		printf(
			'<div class="sb-field-group%s">',
			'textarea' === $field['type'] ? ' sb-field-group--full' : ''
		);

		printf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $field['label'] ) );

		switch ( $field['type'] ) {
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%1$s" class="widefat" rows="4">%2$s</textarea>',
					esc_attr( $id ),
					esc_textarea( (string) $value )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%1$s" class="regular-text">', esc_attr( $id ) );

				foreach ( $field['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $option_value ),
						selected( $value, $option_value, false ),
						esc_html( $option_label )
					);
				}

				echo '</select>';
				break;

			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%1$s" value="%2$s" class="regular-text" %3$s />',
					esc_attr( $id ),
					esc_attr( (string) $value ),
					$this->numeric_attributes( $field )
				);
				break;

			case 'date':
				printf(
					'<input type="date" id="%1$s" name="%1$s" value="%2$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( (string) $value )
				);
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( (string) $value )
				);
				break;
		}

		if ( ! empty( $field['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $field['description'] ) );
		}

		echo '</div>';
	}

	/**
	 * Build an already-escaped "min=... max=... step=..." attribute
	 * fragment for a number input, omitting any bound the field doesn't declare.
	 *
	 * @param array<string, mixed> $field Field definition, may contain "min", "max", "step".
	 */
	private function numeric_attributes( array $field ): string {
		$attributes = array();

		foreach ( array( 'min', 'max', 'step' ) as $attribute ) {
			if ( isset( $field[ $attribute ] ) ) {
				$attributes[] = sprintf( '%s="%s"', $attribute, esc_attr( (string) $field[ $attribute ] ) );
			}
		}

		return implode( ' ', $attributes );
	}

	/**
	 * Field keys grouped into display sections, in render order.
	 *
	 * @return array<string, array{title: string, fields: string[]}>
	 */
	private function sections(): array {
		return array(
			'identification' => array(
				'title'  => __( 'Identification', 'smartbook' ),
				'fields' => array( 'sb_isbn', 'sb_isbn13', 'sb_pages', 'sb_edition', 'sb_language', 'sb_format' ),
			),
			'condition'       => array(
				'title'  => __( 'Condition & Value', 'smartbook' ),
				'fields' => array( 'sb_condition', 'sb_price', 'sb_purchase_date' ),
			),
			'reading'         => array(
				'title'  => __( 'Reading Progress', 'smartbook' ),
				'fields' => array( 'sb_status', 'sb_progress', 'sb_rating', 'sb_favorite', 'sb_wishlist' ),
			),
			'notes'           => array(
				'title'  => __( 'Notes', 'smartbook' ),
				'fields' => array( 'sb_notes', 'sb_summary' ),
			),
		);
	}

	/**
	 * Definition of every meta field this box manages: label, input
	 * type, and (where relevant) numeric bounds, numeric type, select
	 * options, or a help description.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function fields(): array {
		return array(
			'sb_isbn'          => array(
				'label'       => __( 'ISBN-10', 'smartbook' ),
				'type'        => 'text',
				'description' => __( '10-character International Standard Book Number.', 'smartbook' ),
			),
			'sb_isbn13'        => array(
				'label'       => __( 'ISBN-13', 'smartbook' ),
				'type'        => 'text',
				'description' => __( '13-character International Standard Book Number.', 'smartbook' ),
			),
			'sb_pages'         => array(
				'label'        => __( 'Pages', 'smartbook' ),
				'type'         => 'number',
				'numeric_type' => 'int',
				'min'          => 0,
				'step'         => 1,
			),
			'sb_edition'       => array(
				'label' => __( 'Edition', 'smartbook' ),
				'type'  => 'text',
			),
			'sb_language'      => array(
				'label' => __( 'Language', 'smartbook' ),
				'type'  => 'text',
			),
			'sb_format'        => array(
				'label'   => __( 'Format', 'smartbook' ),
				'type'    => 'select',
				'options' => array(
					''          => __( '— Select —', 'smartbook' ),
					'hardcover' => __( 'Hardcover', 'smartbook' ),
					'paperback' => __( 'Paperback', 'smartbook' ),
					'ebook'     => __( 'eBook', 'smartbook' ),
					'audiobook' => __( 'Audiobook', 'smartbook' ),
				),
			),
			'sb_condition'     => array(
				'label'   => __( 'Condition', 'smartbook' ),
				'type'    => 'select',
				'options' => array(
					''         => __( '— Select —', 'smartbook' ),
					'new'      => __( 'New', 'smartbook' ),
					'like_new' => __( 'Like New', 'smartbook' ),
					'good'     => __( 'Good', 'smartbook' ),
					'fair'     => __( 'Fair', 'smartbook' ),
					'poor'     => __( 'Poor', 'smartbook' ),
				),
			),
			'sb_price'         => array(
				'label'        => __( 'Price', 'smartbook' ),
				'type'         => 'number',
				'numeric_type' => 'float',
				'min'          => 0,
				'step'         => 0.01,
			),
			'sb_purchase_date' => array(
				'label' => __( 'Purchase Date', 'smartbook' ),
				'type'  => 'date',
			),
			'sb_rating'        => array(
				'label'        => __( 'Rating', 'smartbook' ),
				'type'         => 'number',
				'numeric_type' => 'int',
				'min'          => 0,
				'max'          => 5,
				'step'         => 1,
			),
			'sb_status'        => array(
				'label'   => __( 'Reading Status', 'smartbook' ),
				'type'    => 'select',
				'options' => array(
					'unread'    => __( 'Unread', 'smartbook' ),
					'reading'   => __( 'Reading', 'smartbook' ),
					'read'      => __( 'Read', 'smartbook' ),
					'on_hold'   => __( 'On Hold', 'smartbook' ),
					'abandoned' => __( 'Abandoned', 'smartbook' ),
				),
			),
			'sb_progress'      => array(
				'label'        => __( 'Progress (%)', 'smartbook' ),
				'type'         => 'number',
				'numeric_type' => 'int',
				'min'          => 0,
				'max'          => 100,
				'step'         => 1,
			),
			'sb_notes'         => array(
				'label' => __( 'Notes', 'smartbook' ),
				'type'  => 'textarea',
			),
			'sb_summary'       => array(
				'label' => __( 'Summary', 'smartbook' ),
				'type'  => 'textarea',
			),
			'sb_favorite'      => array(
				'label' => __( 'Favorite', 'smartbook' ),
				'type'  => 'checkbox',
			),
			'sb_wishlist'      => array(
				'label' => __( 'On Wishlist', 'smartbook' ),
				'type'  => 'checkbox',
			),
		);
	}
}
