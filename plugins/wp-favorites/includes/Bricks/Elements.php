<?php
/**
 * Bricks element registration.
 *
 * @package WpFavorites
 */

namespace WpFavorites\Bricks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elements {
	/**
	 * Theme button style options, captured from Bricks so the Favorites Toggle
	 * can offer the same style choices as the core Button element.
	 *
	 * @var array<string, string>
	 */
	public static $button_styles = array();

	private $dependency_missing = false;

	public function __construct() {
		add_action( 'init', array( $this, 'init_elements' ), 11 );
		add_action( 'admin_notices', array( $this, 'render_dependency_notice' ) );
		add_filter( 'bricks/setup/control_options', array( $this, 'capture_button_styles' ), 5 );
	}

	/**
	 * Capture the button style options Bricks exposes to the core Button element.
	 *
	 * @param mixed $control_options Bricks control options.
	 * @return mixed
	 */
	public function capture_button_styles( $control_options ) {
		if ( is_array( $control_options ) && ! empty( $control_options['styles'] ) && is_array( $control_options['styles'] ) ) {
			self::$button_styles = $control_options['styles'];
		}

		return $control_options;
	}

	public function init_elements(): void {
		if ( ! $this->is_bricks_available() ) {
			$this->dependency_missing = true;
			return;
		}

		\Bricks\Elements::register_element(
			WP_FAVORITES_PLUGIN_DIR . 'includes/Bricks/Element_Favorite_Toggle.php',
			'wp-favorites-toggle',
			\WpFavorites\Bricks\Element_Favorite_Toggle::class
		);
	}

	public function render_dependency_notice(): void {
		if ( ! $this->dependency_missing || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'WordPress Favorites could not register its Bricks element because Bricks is not active or its API is unavailable.', 'wp-favorites' )
		);
	}

	private function is_bricks_available(): bool {
		return class_exists( '\\Bricks\\Element' ) && class_exists( '\\Bricks\\Elements' ) && method_exists( '\\Bricks\\Elements', 'register_element' );
	}
}
