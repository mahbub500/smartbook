<?php
/**
 * Reader star ratings attached to book comments.
 *
 * @package SmartBook
 */

declare(strict_types=1);

namespace SmartBook\Services;

/**
 * Single source of truth for the "sb_comment_rating" comment meta key a
 * commenter's 1-5 star rating (see Frontend\BookContentDisplay's
 * add_rating_field()/save_comment_rating()) is stored under, and for
 * averaging it across a book's approved comments -- consumed by both
 * BookContentDisplay (the single book page's reader-rating summary) and
 * Admin\Tables\BooksListTable (the "Rating" column on the admin books
 * list), so both agree on exactly the same figure.
 */
final class CommentRating {

	/**
	 * Comment meta key a comment's star rating is stored under.
	 */
	public const META_KEY = 'sb_comment_rating';

	/**
	 * Average of every approved comment's star rating on a book, and how
	 * many ratings that average is based on. array(0.0, 0) when nobody
	 * has rated yet.
	 *
	 * @return array{0: float, 1: int}
	 */
	public static function average( int $post_id ): array {
		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
			)
		);

		$ratings = array();

		foreach ( $comments as $comment ) {
			$rating = (int) get_comment_meta( $comment->comment_ID, self::META_KEY, true );

			if ( $rating >= 1 && $rating <= 5 ) {
				$ratings[] = $rating;
			}
		}

		if ( array() === $ratings ) {
			return array( 0.0, 0 );
		}

		return array( array_sum( $ratings ) / count( $ratings ), count( $ratings ) );
	}
}
