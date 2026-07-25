<?php
/**
 * Plugin settings repository.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Settings;

/**
 * Thin repository around a single autoloaded option.
 *
 * All plugin configuration lives in one option row (rather than one
 * option per field) to keep the number of autoloaded rows WordPress has
 * to fetch on every request to a minimum.
 */
final class Settings {

	/**
	 * Name of the option this repository is backed by.
	 */
	private const OPTION_NAME = 'sb_options';

	/**
	 * Settings API option group name, used by both SettingsServiceProvider
	 * (register_setting()) and Admin\Pages\SettingsPage (settings_fields()).
	 */
	public const OPTION_GROUP = 'sb_options_group';

	/**
	 * Default values used when no stored value exists for a key.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULTS = array(
		'enable_logging' => true,
		'log_level'      => 'error',
	);

	/**
	 * Severity keywords accepted for the "log_level" setting.
	 *
	 * @var string[]
	 */
	private const LOG_LEVELS = array( 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' );

	/**
	 * Current values, defaults merged with whatever is stored in the database.
	 *
	 * @var array<string, mixed>
	 */
	private array $values;

	/**
	 * Load and merge the stored option with the defaults.
	 */
	public function __construct() {
		$stored = get_option( self::OPTION_NAME, array() );

		$this->values = array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Name of the backing option, exposed for use with register_setting().
	 */
	public static function option_name(): string {
		return self::OPTION_NAME;
	}

	/**
	 * All current setting values.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->values;
	}

	/**
	 * Read a single setting.
	 */
	public function get( string $key, mixed $default = null ): mixed {
		return $this->values[ $key ] ?? $default;
	}

	/**
	 * Write a single setting and persist it immediately.
	 */
	public function set( string $key, mixed $value ): void {
		$this->values[ $key ] = $value;
		$this->persist();
	}

	/**
	 * Merge and persist several settings at once.
	 *
	 * @param array<string, mixed> $values Values to merge into the current settings.
	 */
	public function set_many( array $values ): void {
		$this->values = array_merge( $this->values, $values );
		$this->persist();
	}

	/**
	 * Sanitize a raw settings array, e.g. as a register_setting() sanitize
	 * callback or before persisting. Unknown keys are dropped and every
	 * known key is coerced to a safe, expected type.
	 *
	 * @param mixed $values Raw values to sanitize.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $values ): array {
		$values = is_array( $values ) ? $values : array();

		$log_level = isset( $values['log_level'] ) ? (string) $values['log_level'] : self::DEFAULTS['log_level'];

		return array(
			'enable_logging' => (bool) ( $values['enable_logging'] ?? self::DEFAULTS['enable_logging'] ),
			'log_level'      => in_array( $log_level, self::LOG_LEVELS, true ) ? $log_level : self::DEFAULTS['log_level'],
		);
	}

	/**
	 * Persist the in-memory values to the database, sanitized.
	 */
	private function persist(): void {
		update_option( self::OPTION_NAME, $this->sanitize( $this->values ) );
	}
}
