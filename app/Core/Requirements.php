<?php
/**
 * Environment requirement checks.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies the host environment satisfies the plugin's minimum requirements.
 */
final class Requirements {

	/**
	 * Whether both PHP and WordPress satisfy the minimum required versions.
	 */
	public static function satisfied(): bool {
		return self::php_satisfied() && self::wp_satisfied();
	}

	/**
	 * Whether the running PHP version is supported.
	 */
	public static function php_satisfied(): bool {
		return version_compare( PHP_VERSION, SB_MIN_PHP, '>=' );
	}

	/**
	 * Whether the running WordPress version is supported.
	 */
	public static function wp_satisfied(): bool {
		global $wp_version;

		return version_compare( $wp_version, SB_MIN_WP, '>=' );
	}

	/**
	 * Human readable explanation of the first unmet requirement.
	 */
	public static function unsatisfied_message(): string {
		global $wp_version;

		if ( ! self::php_satisfied() ) {
			return sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				esc_html__( 'SmartBook requires PHP %1$s or higher. You are running PHP %2$s.', 'smartbook' ),
				SB_MIN_PHP,
				PHP_VERSION
			);
		}

		return sprintf(
			/* translators: 1: required WordPress version, 2: current WordPress version. */
			esc_html__( 'SmartBook requires WordPress %1$s or higher. You are running WordPress %2$s.', 'smartbook' ),
			SB_MIN_WP,
			$wp_version
		);
	}
}
