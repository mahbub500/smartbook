<?php
/**
 * File-based logger.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Services;

use Stringable;

/**
 * Writes leveled log entries to a locked-down directory inside the
 * uploads folder, one file per day. Never throws: a failure to write a
 * log entry must never break the request that triggered it.
 */
final class Logger implements LoggerInterface {

	/**
	 * Severity order, lowest number is most severe (RFC 5424 style).
	 *
	 * @var array<string, int>
	 */
	private const LEVELS = array(
		'emergency' => 0,
		'alert'     => 1,
		'critical'  => 2,
		'error'     => 3,
		'warning'   => 4,
		'notice'    => 5,
		'info'      => 6,
		'debug'     => 7,
	);

	/**
	 * Whether the directory has already been prepared this request.
	 */
	private bool $directory_ready = false;

	/**
	 * @param string $directory Absolute path to the directory log files are written into.
	 * @param bool   $enabled   Whether logging is active at all.
	 * @param string $threshold Minimum severity keyword that gets written; anything less severe is discarded.
	 */
	public function __construct(
		private readonly string $directory,
		private bool $enabled = true,
		private string $threshold = 'error'
	) {
	}

	/**
	 * Enable or disable logging at runtime.
	 */
	public function set_enabled( bool $enabled ): void {
		$this->enabled = $enabled;
	}

	/**
	 * Change the minimum severity that gets written.
	 */
	public function set_threshold( string $threshold ): void {
		if ( isset( self::LEVELS[ $threshold ] ) ) {
			$this->threshold = $threshold;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function emergency( string $message, array $context = array() ): void {
		$this->log( 'emergency', $message, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function alert( string $message, array $context = array() ): void {
		$this->log( 'alert', $message, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function critical( string $message, array $context = array() ): void {
		$this->log( 'critical', $message, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function notice( string $message, array $context = array() ): void {
		$this->log( 'notice', $message, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! $this->enabled ) {
			return;
		}

		$level = isset( self::LEVELS[ $level ] ) ? $level : 'info';

		if ( self::LEVELS[ $level ] > self::LEVELS[ $this->threshold ] ) {
			return;
		}

		if ( ! $this->prepare_directory() ) {
			return;
		}

		$line = $this->format( $level, $message, $context );
		$file = trailingslashit( $this->directory ) . 'sb-' . gmdate( 'Y-m-d' ) . '.log';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Interpolate context values into "{placeholder}" tokens and append
	 * a timestamped, leveled line ready to be written to disk.
	 *
	 * @param string               $level   Severity keyword.
	 * @param string               $message Raw message, may contain "{placeholder}" tokens.
	 * @param array<string, mixed> $context Values to interpolate and append as detail.
	 */
	private function format( string $level, string $message, array $context ): string {
		$replacements = array();

		foreach ( $context as $key => $value ) {
			$replacements[ '{' . $key . '}' ] = $this->stringify( $value );
		}

		$interpolated = strtr( $message, $replacements );

		$line = sprintf(
			'[%s] [%s] %s',
			gmdate( 'Y-m-d H:i:s' ),
			strtoupper( $level ),
			$interpolated
		);

		if ( array() !== $context ) {
			$encoded = wp_json_encode( $context );
			$line   .= ' ' . ( false !== $encoded ? $encoded : '' );
		}

		return $line . PHP_EOL;
	}

	/**
	 * Render an arbitrary context value as a log-safe string.
	 */
	private function stringify( mixed $value ): string {
		if ( $value instanceof Stringable || is_scalar( $value ) ) {
			return (string) $value;
		}

		if ( $value instanceof \Throwable ) {
			return $value->getMessage();
		}

		$encoded = wp_json_encode( $value );

		return false !== $encoded ? $encoded : '';
	}

	/**
	 * Ensure the log directory exists and is not publicly browsable or
	 * directly servable, creating the guard files on first use.
	 */
	private function prepare_directory(): bool {
		if ( $this->directory_ready ) {
			return true;
		}

		if ( ! wp_mkdir_p( $this->directory ) ) {
			return false;
		}

		$index_file = trailingslashit( $this->directory ) . 'index.php';

		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		$htaccess_file = trailingslashit( $this->directory ) . '.htaccess';

		if ( ! file_exists( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $htaccess_file, "Require all denied\n" );
		}

		$this->directory_ready = true;

		return true;
	}
}
