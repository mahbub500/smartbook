<?php
/**
 * Hookable contract.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Core\Contracts;

/**
 * Implemented by any class that attaches its own WordPress actions or
 * filters. Standardising on a single register_hooks() entry point lets
 * service providers boot a collection of unrelated objects uniformly.
 */
interface Hookable {

	/**
	 * Attach the class's WordPress actions and filters.
	 */
	public function register_hooks(): void;
}
