<?php
/**
 * Shared settings page base for WordPress plugins.
 *
 * @package WpPluginShared
 * @since   0.1.0
 */

namespace WpPluginShared;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Page {

	protected string $menu_slug;
	protected string $page_title;
	protected string $menu_title;
	protected string $option_name;
	protected string $settings_group;

	public function __construct( array $args ) {
		$this->menu_slug      = $args['menu_slug'] ?? '';
		$this->page_title    = $args['page_title'] ?? '';
		$this->menu_title    = $args['menu_title'] ?? '';
		$this->option_name   = $args['option_name'] ?? '';
		$this->settings_group = $args['settings_group'] ?? '';

		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_menu_page(): void {
		add_options_page(
			$this->page_title,
			$this->menu_title,
			'manage_options',
			$this->menu_slug,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			$this->settings_group,
			$this->option_name,
			[
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => $this->get_defaults(),
			]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $this->page_title ) . '</h1>';
		echo '<form action="options.php" method="post">';
		settings_fields( $this->settings_group );
		do_settings_sections( $this->menu_slug );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	public function sanitize( $input ): array {
		return is_array( $input ) ? $input : $this->get_defaults();
	}

	protected function get_defaults(): array {
		return [];
	}

	public function get_option_name(): string {
		return $this->option_name;
	}

	public function get_settings_group(): string {
		return $this->settings_group;
	}
}
