<?php
/**
 * Frontend asset registration and localization.
 *
 * @package WpFavorites
 */

namespace WpFavorites;

use WpFavorites\Rest\Rest_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {
	private const SCRIPT_HANDLE = 'wp-favorites-toggle';

	/**
	 * Enqueue the toggle script. Called only when the Bricks Favorite Toggle
	 * element is rendered. The toggle ships no styles of its own: it inherits
	 * the Bricks Button styles via its `bricks-button` classes.
	 */
	public function enqueue_toggle_assets(): void {
		$script_path = WP_FAVORITES_PLUGIN_DIR . 'assets/js/wp-favorites-toggle.js';

		if ( ! file_exists( $script_path ) ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			WP_FAVORITES_PLUGIN_URL . 'assets/js/wp-favorites-toggle.js',
			array(),
			(string) filemtime( $script_path ),
			true
		);

		wp_localize_script( self::SCRIPT_HANDLE, 'WpFavoritesRuntime', $this->get_runtime_config() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_runtime_config(): array {
		return array(
			'restUrl'     => esc_url_raw( rest_url( Rest_Controller::REST_NAMESPACE . Rest_Controller::TOGGLE_ROUTE ) ),
			'listUrl'     => esc_url_raw( rest_url( Rest_Controller::REST_NAMESPACE . Rest_Controller::LIST_ROUTE ) ),
			'restNonce'   => wp_create_nonce( 'wp_rest' ),
			'refreshMode' => apply_filters( 'wp_favorites_refresh_mode', 'dom' ),
			'strings'     => array(
				'error' => esc_html__( 'Could not update favorites. Please try again.', 'wp-favorites' ),
			),
		);
	}
}
