<?php
/**
 * Dependency injection container.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core\Container;

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
	 */
	public function bind( string $abstract, Closure|string $concrete ): void {
		$this->bindings[ $abstract ] = array(
			'concrete' => $concrete,
			'shared'   => false,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function singleton( string $abstract, Closure|string $concrete ): void {
		$this->bindings[ $abstract ] = array(
			'concrete' => $concrete,
			'shared'   => true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function instance( string $abstract, object $instance ): void {
		$this->instances[ $abstract ] = $instance;
	}

	/**
	 * {@inheritDoc}
	 */
	public function make( string $abstract, array $parameters = array() ): mixed {
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		$binding = $this->bindings[ $abstract ] ?? null;

		if ( null === $binding ) {
			if ( ! class_exists( $abstract ) ) {
				throw new NotFoundException(
					sprintf( 'No binding or class found for "%s".', $abstract )
				);
			}

			return $this->build( $abstract, $parameters );
		}

		$concrete = $binding['concrete'];

		$object = $concrete instanceof Closure
			? $concrete( $this, $parameters )
			: $this->build( $concrete, $parameters );

		if ( $binding['shared'] ) {
			$this->instances[ $abstract ] = $object;
		}

		return $object;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get( string $id ): mixed {
		return $this->make( $id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] )
			|| isset( $this->bindings[ $id ] )
			|| class_exists( $id );
	}

	/**
	 * Instantiate a class, autowiring its constructor dependencies.
	 *
	 * @param string               $class      Fully qualified class name.
	 * @param array<string, mixed> $parameters Explicit constructor parameters, keyed by name.
	 *
	 * @return object
	 */
	private function build( string $class, array $parameters ): object {
		try {
			$reflection = new ReflectionClass( $class );
		} catch ( ReflectionException $exception ) {
			throw new ContainerException(
				sprintf( 'Class "%s" does not exist.', $class ),
				0,
				$exception
			);
		}

		if ( ! $reflection->isInstantiable() ) {
			throw new ContainerException(
				sprintf( 'Class "%s" is not instantiable.', $class )
			);
		}

		$constructor = $reflection->getConstructor();

		if ( null === $constructor ) {
			return new $class();
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
					$name,
					$class
				)
			);
		}

		return $reflection->newInstanceArgs( $dependencies );
	}
}
