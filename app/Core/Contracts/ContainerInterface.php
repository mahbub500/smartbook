<?php
/**
 * Dependency injection container contract.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Closure;

/**
 * Minimal, framework-agnostic service container contract.
 *
 * Keeping this as an interface (rather than depending on a concrete
 * container) lets every consumer of the container type-hint against a
 * stable contract, which keeps modules loosely coupled and testable.
 */
interface ContainerInterface {

	/**
	 * Register a binding, resolved fresh on every call to make()/get().
	 *
	 * @param string         $abstract_id Identifier to bind (usually a class or interface name).
	 * @param Closure|string $concrete Factory closure or a class name to instantiate.
	 */
	public function bind( string $abstract_id, Closure|string $concrete ): void;

	/**
	 * Register a binding that is resolved once and then reused.
	 *
	 * @param string         $abstract_id Identifier to bind (usually a class or interface name).
	 * @param Closure|string $concrete Factory closure or a class name to instantiate.
	 */
	public function singleton( string $abstract_id, Closure|string $concrete ): void;

	/**
	 * Register an already-constructed object as a shared instance.
	 *
	 * @param string $abstract_id Identifier to bind.
	 * @param object $instance Fully constructed instance.
	 */
	public function instance( string $abstract_id, object $instance ): void;

	/**
	 * Resolve an identifier out of the container, autowiring constructor
	 * dependencies via reflection when no explicit binding exists.
	 *
	 * @param string               $abstract_id   Identifier to resolve.
	 * @param array<string, mixed> $parameters Explicit constructor parameters, keyed by name.
	 *
	 * @return mixed
	 */
	public function make( string $abstract_id, array $parameters = array() ): mixed;

	/**
	 * PSR-11 style alias for make() with no parameters.
	 *
	 * @param string $id Identifier to resolve.
	 *
	 * @return mixed
	 */
	public function get( string $id ): mixed;

	/**
	 * Whether the container can resolve the given identifier.
	 *
	 * @param string $id Identifier to check.
	 */
	public function has( string $id ): bool;
}
