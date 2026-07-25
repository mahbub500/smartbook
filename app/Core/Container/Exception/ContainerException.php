<?php
/**
 * Thrown when the container fails to build a resolvable identifier.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core\Container\Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

/**
 * Raised by Container::make() when an identifier is resolvable in
 * principle (a binding or class exists) but construction fails, e.g. an
 * un-instantiable class or an unresolvable constructor parameter.
 */
final class ContainerException extends RuntimeException {

}
