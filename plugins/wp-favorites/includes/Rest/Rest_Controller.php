<?php
/**
 * Favorites REST API.
 *
 * @package WpFavorites
 */

namespace WpFavorites\Rest;

use WpFavorites\Favorites_Store;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Controller {
	public const REST_NAMESPACE = 'wp-favorites/v1';
	public const LIST_ROUTE     = '/favorites';
	public const TOGGLE_ROUTE   = '/favorites/toggle';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::LIST_ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_favorites' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::TOGGLE_ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'toggle' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'post_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => static function ( $value ): bool {
								return absint( $value ) > 0;
							},
						),
						'mode'    => array(
							'type'    => 'string',
							'default' => 'toggle',
							'enum'    => array( 'add', 'remove', 'toggle' ),
						),
					),
				),
			)
		);
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $_request is required by the WP REST API callback signature.
	public function get_favorites( WP_REST_Request $_request ): WP_REST_Response {
		$ids = Favorites_Store::get_ids();

		$response = new WP_REST_Response(
			array(
				'ids'   => array_values( $ids ),
				'count' => count( $ids ),
			)
		);

		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	public function toggle( WP_REST_Request $request ): WP_REST_Response {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! Favorites_Store::is_favoritable( $post_id ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'wp_favorites_invalid_post',
					'message' => esc_html__( 'The requested post cannot be favorited.', 'wp-favorites' ),
				),
				400
			);
		}

		$mode = (string) $request->get_param( 'mode' );

		if ( 'add' === $mode ) {
			$state = Favorites_Store::add( $post_id );
		} elseif ( 'remove' === $mode ) {
			$state = Favorites_Store::remove( $post_id );
		} else {
			$state = Favorites_Store::toggle( $post_id );
		}

		$ids = Favorites_Store::get_ids();

		$response = new WP_REST_Response(
			array(
				'postId'     => $post_id,
				'isFavorite' => (bool) $state,
				'ids'        => array_values( $ids ),
				'count'      => count( $ids ),
			)
		);

		if ( ! empty( $request->get_header( 'X-WP-Nonce' ) ) ) {
			$response->header( 'Cache-Control', 'no-store' );
		}

		return $response;
	}
}
