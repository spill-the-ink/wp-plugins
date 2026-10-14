<?php
/**
 * Bricks query loop source for favorited posts.
 *
 * @package WpFavorites
 */

namespace WpFavorites\Bricks;

use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query {
	public const OBJECT_TYPE = 'wp_favorites';

	/**
	 * Upper bound exposed to Bricks' pagination element.
	 */
	public const MAX_NUM_PAGES = 100;

	public function __construct() {
		add_filter( 'bricks/setup/control_options', array( $this, 'register_query_type' ) );
		add_filter( 'bricks/query/run', array( $this, 'run_query' ), 10, 2 );
		add_filter( 'bricks/query/loop_object', array( $this, 'loop_object' ), 10, 3 );
		add_filter( 'bricks/query/result_max_num_pages', array( $this, 'result_max_num_pages' ), 10, 2 );
	}

	/**
	 * Register the "Favorites" query type in the query loop UI.
	 *
	 * @param mixed $control_options Bricks control options.
	 * @return mixed
	 */
	public function register_query_type( $control_options ) {
		if ( ! is_array( $control_options ) ) {
			return $control_options;
		}

		$control_options['queryTypes'][ self::OBJECT_TYPE ] = esc_html__( 'Favorites', 'wp-favorites' );

		return $control_options;
	}

	/**
	 * Resolve the favorites query into an ordered list of posts.
	 *
	 * @param mixed $results   Preset results.
	 * @param mixed $query_obj Bricks query object.
	 * @return mixed
	 */
	public function run_query( $results, $query_obj ) {
		if ( ! $this->is_favorites_query( $query_obj ) ) {
			return $results;
		}

		$ids = wp_favorites_get_ids();

		if ( empty( $ids ) ) {
			return array();
		}

		$per_page = isset( $query_obj->query_vars['posts_per_page'] )
			? (int) $query_obj->query_vars['posts_per_page']
			: -1;

		$query = new WP_Query(
			array(
				'post_type'           => (array) apply_filters( 'wp_favorites_query_post_types', array_keys( get_post_types( array( 'public' => true ) ) ) ),
				'post_status'         => 'publish',
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'ignore_sticky_posts' => true,
				'posts_per_page'      => $per_page > 0 ? $per_page : -1,
				'paged'               => $this->get_paged( $query_obj ),
			)
		);

		return $query->posts;
	}

	/**
	 * Set up post data for template tags inside the loop.
	 *
	 * @param mixed $loop_object Loop object.
	 * @param mixed $loop_key    Loop key.
	 * @param mixed $query_obj   Bricks query object.
	 * @return mixed
	 */
	public function loop_object( $loop_object, $loop_key, $query_obj ) {
		if ( ! $this->is_favorites_query( $query_obj ) || ! $loop_object instanceof WP_Post ) {
			return $loop_object;
		}

		global $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional WordPress setup_postdata pattern.
		$post = $loop_object;
		setup_postdata( $post );

		return $loop_object;
	}

	/**
	 * @param mixed $max_num_pages Max pages.
	 * @param mixed $query_obj     Bricks query object.
	 * @return mixed
	 */
	public function result_max_num_pages( $max_num_pages, $query_obj ) {
		if ( $this->is_favorites_query( $query_obj ) ) {
			return self::MAX_NUM_PAGES;
		}

		return $max_num_pages;
	}

	/**
	 * @param mixed $query_obj Bricks query object.
	 */
	private function is_favorites_query( $query_obj ): bool {
		return is_object( $query_obj ) && self::OBJECT_TYPE === (string) ( $query_obj->object_type ?? '' );
	}

	/**
	 * @param mixed $query_obj Bricks query object.
	 */
	private function get_paged( $query_obj ): int {
		// Custom object types don't go through Bricks' per-type paged resolution.
		if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_paged_query_var' ) ) {
			return max( 1, (int) \Bricks\Query::get_paged_query_var( array() ) );
		}

		if ( is_object( $query_obj ) && isset( $query_obj->query_vars['paged'] ) ) {
			return max( 1, (int) $query_obj->query_vars['paged'] );
		}

		return 1;
	}
}
