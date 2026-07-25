<?php
/**
 * Canonical schema for the book's custom meta fields.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\MetaBoxes;

use DateTimeImmutable;

/**
 * Single source of truth for the sixteen "sb_"-prefixed book meta
 * fields: their labels, input types, and sanitization rules. Consumed
 * by BookDetailsMetaBox (edit-screen UI) and Admin\Pages\ImportExportPage
 * (CSV import/export) so both stay in lock-step with the same field set
 * instead of maintaining two divergent copies.
 */
final class BookFields {

	/**
	 * Definition of every meta field: label, input type, and (where
	 * relevant) numeric bounds/type, select options, or a help description.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions(): array {
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
			'sb_barcode'       => array(
				'label'       => __( 'Barcode', 'smartbook' ),
				'type'        => 'text',
				'description' => __( 'Value encoded in this book\'s Code128 barcode. Leave blank to auto-assign one.', 'smartbook' ),
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
			'sb_borrowed'      => array(
				'label'       => __( 'Borrowed', 'smartbook' ),
				'type'        => 'checkbox',
				'description' => __( 'This copy is on loan (borrowed from or lent to someone else).', 'smartbook' ),
			),
		);
	}

	/**
	 * Sanitize a raw value for a given field key according to its
	 * declared type. Unknown keys fall back to plain text sanitization.
	 *
	 * @param string $key Meta key, e.g. "sb_isbn".
	 * @param mixed  $raw Raw value (already unslashed by the caller).
	 */
	public static function sanitize( string $key, mixed $raw ): string|int|float {
		$field = self::definitions()[ $key ] ?? array( 'type' => 'text' );

		return match ( $field['type'] ) {
			'textarea' => sanitize_textarea_field( (string) $raw ),
			'select'   => self::sanitize_choice( (string) $raw, $field['options'] ?? array() ),
			'number'   => self::sanitize_number( $raw, $field ),
			'date'     => self::sanitize_date( (string) $raw ),
			'checkbox' => self::sanitize_checkbox( $raw ),
			default    => sanitize_text_field( (string) $raw ),
		};
	}

	/**
	 * Keep only a value that matches one of a select field's known
	 * option keys; anything else is discarded.
	 *
	 * @param array<string, string> $options Allowed option value => label pairs.
	 */
	private static function sanitize_choice( string $value, array $options ): string {
		return array_key_exists( $value, $options ) ? $value : '';
	}

	/**
	 * Coerce and clamp a numeric field to its declared type and bounds.
	 *
	 * @param mixed                $raw   Raw value.
	 * @param array<string, mixed> $field Field definition, may contain "numeric_type", "min", "max".
	 */
	private static function sanitize_number( mixed $raw, array $field ): int|float {
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
	private static function sanitize_date( string $raw ): string {
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
	 * Normalize any truthy representation ("1", "true", "yes", "on", or
	 * an actual boolean) to "1", everything else to an empty string.
	 */
	private static function sanitize_checkbox( mixed $raw ): string {
		if ( is_bool( $raw ) ) {
			return $raw ? '1' : '';
		}

		$normalized = strtolower( trim( (string) $raw ) );

		return in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ? '1' : '';
	}
}
