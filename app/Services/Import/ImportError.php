<?php
/**
 * A single failed-row entry in an import/restore run's error log.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Services\Import;

/**
 * Immutable record of one row that could not be imported, kept for the
 * on-screen error log and its downloadable export.
 */
final class ImportError {

	public function __construct(
		public readonly int $row_number,
		public readonly string $title,
		public readonly string $message
	) {
	}

	/**
	 * @return array{row: int, title: string, message: string}
	 */
	public function to_array(): array {
		return array(
			'row'     => $this->row_number,
			'title'   => $this->title,
			'message' => $this->message,
		);
	}

	/**
	 * @param array<string, mixed> $data Array shaped like to_array()'s return.
	 */
	public static function from_array( array $data ): self {
		return new self(
			(int) ( $data['row'] ?? 0 ),
			(string) ( $data['title'] ?? '' ),
			(string) ( $data['message'] ?? '' )
		);
	}
}
