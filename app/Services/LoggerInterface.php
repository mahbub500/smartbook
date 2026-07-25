<?php
/**
 * Logger contract.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Services;

/**
 * A minimal, PSR-3-shaped leveled logger contract. Kept as an interface
 * so any consumer can be tested against a fake logger instead of writing
 * to disk.
 */
interface LoggerInterface {

	/**
	 * System is unusable.
	 *
	 * @param string               $message Log message, may contain "{placeholder}" tokens.
	 * @param array<string, mixed> $context Values to interpolate into the message and append as detail.
	 */
	public function emergency( string $message, array $context = array() ): void;

	/**
	 * Action must be taken immediately.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Contextual values.
	 */
	public function alert( string $message, array $context = array() ): void;

	/**
	 * Critical conditions.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Contextual values.
	 */
	public function critical( string $message, array $context = array() ): void;

	/**
	 * Runtime errors that do not require immediate action.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Contextual values.
	 */
	public function error( string $message, array $context = array() ): void;

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Contextual values.
	 */
	public function warning( string $message, array $context = array() ): void;

	/**
	 * Normal but significant events.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Contextual values.
	 */
	public function notice( string $message, array $context = array() ): void;

	/**
	 * Interesting events.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Contextual values.
	 */
	public function info( string $message, array $context = array() ): void;

	/**
	 * Detailed debug information.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Contextual values.
	 */
	public function debug( string $message, array $context = array() ): void;

	/**
	 * Log at an arbitrary level.
	 *
	 * @param string               $level   One of the RFC 5424 severity keywords.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Contextual values.
	 */
	public function log( string $level, string $message, array $context = array() ): void;
}
