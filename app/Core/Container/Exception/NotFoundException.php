<?php
/**
 * Thrown when the container cannot resolve a requested identifier.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core\Container\Exception;

use RuntimeException;

/**
 * Raised by Container::make() when an identifier has no binding and does
 * not correspond to an existing, loadable class.
 */
final class NotFoundException extends RuntimeException {

}
