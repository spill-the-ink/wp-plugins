<?php
/**
 * Public procedural API for WordPress Favorites.
 *
 * @package WpFavorites
 */

use WpFavorites\Favorites_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the favorited post IDs for the current visitor.
 *
 * @return int[] Ordered list of post IDs.
 */
function wp_favorites_get_ids(): array {
	return Favorites_Store::get_ids();
}

/**
 * Whether a post is favorited by the current visitor.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function wp_favorites_has( int $post_id ): bool {
	return Favorites_Store::has( $post_id );
}

/**
 * Add a post to the favorites list. Returns the resulting state (always true on success).
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function wp_favorites_add( int $post_id ): bool {
	return Favorites_Store::add( $post_id );
}

/**
 * Remove a post from the favorites list. Returns the resulting state (always false on success).
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function wp_favorites_remove( int $post_id ): bool {
	return Favorites_Store::remove( $post_id );
}

/**
 * Toggle a post in the favorites list. Returns the new state.
 *
 * @param int $post_id Post ID.
 * @return bool True if the post is now a favorite, false otherwise.
 */
function wp_favorites_toggle( int $post_id ): bool {
	return Favorites_Store::toggle( $post_id );
}

/**
 * Whether a post is eligible to be favorited.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function wp_favorites_is_favoritable( int $post_id ): bool {
	return Favorites_Store::is_favoritable( $post_id );
}
