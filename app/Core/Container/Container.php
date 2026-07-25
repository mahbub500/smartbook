<?php
/**
 * Dependency injection container.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use SmartBook\Core\Container\Exception\ContainerException;
use SmartBook\Core\Container\Exception\NotFoundException;
use SmartBook\Core\Contracts\ContainerInterface;

/**
 * A small, reflection-based dependency injection container.
 *
 * Supports transient bindings, shared (singleton) bindings, pre-built
 * instances, and constructor autowiring for classes that were never
 * explicitly bound. Autowiring only resolves typed, non-builtin
 * constructor parameters; scalar parameters must either have a default
 * value or be supplied explicitly via make()'s $parameters argument.
 */
final class Container implements ContainerInterface {

	/**
	 * Registered bindings, keyed by abstract identifier.
	 *
	 * @var array<string, array{concrete: Closure|string, shared: bool}>
	 */
	private array $bindings = array();

	/**
	 * Resolved shared instances, keyed by abstract identifier.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param string         $abstract_id Identifier to bind (usually a class or interface name).
	 * @param Closure|string $concrete    Factory closure or a class name to instantiate.
	 */
	public function bind( string $abstract_id, Closure|string $concrete ): void {
		$this->bindings[ $abstract_id ] = array(
			'concrete' => $concrete,
			'shared'   => false,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string         $abstract_id Identifier to bind (usually a class or interface name).
	 * @param Closure|string $concrete    Factory closure or a class name to instantiate.
	 */
	public function singleton( string $abstract_id, Closure|string $concrete ): void {
		$this->bindings[ $abstract_id ] = array(
			'concrete' => $concrete,
			'shared'   => true,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $abstract_id Identifier to bind.
	 * @param object $instance    Fully constructed instance.
	 */
	public function instance( string $abstract_id, object $instance ): void {
		$this->instances[ $abstract_id ] = $instance;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string               $abstract_id Identifier to resolve.
	 * @param array<string, mixed> $parameters  Explicit constructor parameters, keyed by name.
	 *
	 * @throws NotFoundException When no binding or class exists for the identifier.
	 */
	public function make( string $abstract_id, array $parameters = array() ): mixed {
		if ( isset( $this->instances[ $abstract_id ] ) ) {
			return $this->instances[ $abstract_id ];
		}

		$binding = $this->bindings[ $abstract_id ] ?? null;

		if ( null === $binding ) {
			if ( ! class_exists( $abstract_id ) ) {
				throw new NotFoundException(
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer-facing exception message, escaped via esc_html() where a boot failure is displayed (see smartbook.php), not echoed here.
					sprintf( 'No binding or class found for "%s".', $abstract_id )
				);
			}

			return $this->build( $abstract_id, $parameters );
		}

		$concrete = $binding['concrete'];

		$object = $concrete instanceof Closure
			? $concrete( $this, $parameters )
			: $this->build( $concrete, $parameters );

		if ( $binding['shared'] ) {
			$this->instances[ $abstract_id ] = $object;
		}

		return $object;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $id Identifier to resolve.
	 */
	public function get( string $id ): mixed {
		return $this->make( $id );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $id Identifier to check.
	 */
	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] )
			|| isset( $this->bindings[ $id ] )
			|| class_exists( $id );
	}

	/**
	 * Instantiate a class, autowiring its constructor dependencies.
	 *
	 * @param string               $class_name      Fully qualified class name.
	 * @param array<string, mixed> $parameters Explicit constructor parameters, keyed by name.
	 *
	 * @return object
	 *
	 * @throws ContainerException When the class cannot be reflected, is not instantiable, or a constructor parameter cannot be resolved.
	 */
	private function build( string $class_name, array $parameters ): object {
		try {
			$reflection = new ReflectionClass( $class_name );
		} catch ( ReflectionException $exception ) {
			throw new ContainerException(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer-facing exception message, escaped via esc_html() where a boot failure is displayed (see smartbook.php), not echoed here.
				sprintf( 'Class "%s" does not exist.', $class_name ),
				0,
				$exception // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- previous-exception constructor argument, not output.
			);
		}

		if ( ! $reflection->isInstantiable() ) {
			throw new ContainerException(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer-facing exception message, escaped via esc_html() where a boot failure is displayed (see smartbook.php), not echoed here.
				sprintf( 'Class "%s" is not instantiable.', $class_name )
			);
		}

		$constructor = $reflection->getConstructor();

		if ( null === $constructor ) {
			return new $class_name();
		}

		$dependencies = array();

		foreach ( $constructor->getParameters() as $parameter ) {
			$name = $parameter->getName();

			if ( array_key_exists( $name, $parameters ) ) {
				$dependencies[] = $parameters[ $name ];
				continue;
			}

			$type = $parameter->getType();

			if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
				$dependencies[] = $this->make( $type->getName() );
				continue;
			}

			if ( $parameter->isDefaultValueAvailable() ) {
				$dependencies[] = $parameter->getDefaultValue();
				continue;
			}

			throw new ContainerException(
				sprintf(
					'Cannot resolve parameter "$%s" for class "%s".',
					$name, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer-facing exception message, escaped via esc_html() where a boot failure is displayed (see smartbook.php), not echoed here.
					$class_name // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- same as above.
				)
			);
		}

		return $reflection->newInstanceArgs( $dependencies );
	}
}
