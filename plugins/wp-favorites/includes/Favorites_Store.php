<?php
/**
 * Favorites persistence.
 *
 * There are no user accounts associated with favorites, so every visitor is
 * treated the same way: favorites are stored in a single cookie so they resolve
 * server-side (required for the Bricks query loop).
 *
 * @package WpFavorites
 */

namespace WpFavorites;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Favorites_Store {
	public const COOKIE_NAME = 'wp_favorites_ids';
	public const DEFAULT_MAX = 300;

	/**
	 * Get the ordered list of favorited post IDs.
	 *
	 * @return int[]
	 */
	public static function get_ids(): array {
		return self::get_cookie_ids();
	}

	/**
	 * Whether a post is favorited.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function has( int $post_id ): bool {
		return in_array( $post_id, self::get_ids(), true );
	}

	/**
	 * Add a post to the favorites list.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Always true.
	 */
	public static function add( int $post_id ): bool {
		$ids   = self::get_ids();
		$ids[] = $post_id;

		self::set_ids( $ids );

		return true;
	}

	/**
	 * Remove a post from the favorites list.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Always false.
	 */
	public static function remove( int $post_id ): bool {
		$ids = array_values( array_diff( self::get_ids(), array( $post_id ) ) );

		self::set_ids( $ids );

		return false;
	}

	/**
	 * Toggle a post in the favorites list.
	 *
	 * @param int $post_id Post ID.
	 * @return bool The new state.
	 */
	public static function toggle( int $post_id ): bool {
		return self::has( $post_id )
			? self::remove( $post_id )
			: self::add( $post_id );
	}

	/**
	 * Persist an ordered list of IDs.
	 *
	 * @param int[] $ids Ordered post IDs.
	 */
	public static function set_ids( array $ids ): void {
		$ids = array_values( array_slice( self::sanitize_ids( $ids ), -self::max() ) );

		self::set_cookie_ids( $ids );
	}

	/**
	 * Whether a post is eligible to be favorited.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function is_favoritable( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		if ( '' !== (string) $post->post_password ) {
			return false;
		}

		$allowed = array_keys( get_post_types( array( 'public' => true ) ) );
		$allowed = (array) apply_filters( 'wp_favorites_allowed_post_types', $allowed );

		return in_array( $post->post_type, $allowed, true );
	}

	/**
	 * @param array $ids Raw IDs.
	 * @return int[]
	 */
	private static function sanitize_ids( array $ids ): array {
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private static function max(): int {
		return max( 1, (int) apply_filters( 'wp_favorites_max_ids', self::DEFAULT_MAX ) );
	}

	/**
	 * @return int[]
	 */
	private static function get_cookie_ids(): array {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return array();
		}

		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		return self::sanitize_ids( explode( ',', $raw ) );
	}

	/**
	 * @param int[] $ids Sanitized, ordered IDs.
	 */
	private static function set_cookie_ids( array $ids ): void {
		if ( headers_sent() ) {
			return;
		}

		$value = implode( ',', $ids );

		setcookie(
			self::COOKIE_NAME,
			$value,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		// Keep same-request reads consistent.
		$_COOKIE[ self::COOKIE_NAME ] = $value;
	}
}
