<?php
/**
 * Settings page for WordPress Inline CSS Optimizer.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Page extends \WpPluginShared\Settings_Page {

	public function __construct() {
		parent::__construct(
			array(
				'menu_slug'      => 'wp-inline-css-optimizer',
				'page_title'     => 'Inline CSS Optimizer',
				'menu_title'     => 'Inline CSS',
				'option_name'    => WP_INLINE_CSS_OPTIMIZER_OPTION,
				'settings_group' => WP_INLINE_CSS_OPTIMIZER_OPTION,
				'form_id'        => 'wp-inline-css-optimizer-settings',
			)
		);

		$this->register_tabs();

		add_action( 'admin_init', array( $this, 'register_fields' ), 5 );
		add_action( 'update_option_' . WP_INLINE_CSS_OPTIMIZER_OPTION, array( $this, 'on_option_updated' ) );
	}

	private function register_tabs(): void {
		$this->add_tab( 'general', esc_html__( 'Optimization', 'wp-inline-css-optimizer' ), 'dashicons-performance' );
	}

	/**
	 * Register all settings fields.
	 */
	public function register_fields(): void {
		$this->add_section(
			'optimization',
			esc_html__( 'Inline CSS', 'wp-inline-css-optimizer' ),
			'general',
			esc_html__( 'Inline CSS registered by themes and plugins is minified just before the styles are printed.', 'wp-inline-css-optimizer' )
		);

		$this->add_field( 'enabled', esc_html__( 'Enable optimization', 'wp-inline-css-optimizer' ), 'checkbox', 'optimization' );

		$this->add_field(
			'aggressive',
			esc_html__( 'Aggressive whitespace removal', 'wp-inline-css-optimizer' ),
			'checkbox',
			'optimization',
			array(
				'description' => esc_html__( 'Also remove whitespace around { } ; , and after colons. Strings, url() values and calc() expressions are always preserved.', 'wp-inline-css-optimizer' ),
				'priority'    => 20,
			)
		);

		$this->add_field(
			'min_bytes',
			esc_html__( 'Minimum size (bytes)', 'wp-inline-css-optimizer' ),
			'number',
			'optimization',
			array(
				'description' => esc_html__( 'Skip inline blocks smaller than this to avoid needless work. 0 disables the threshold.', 'wp-inline-css-optimizer' ),
				'min'         => 0,
				'max'         => 1000000,
				'priority'    => 30,
			)
		);

		$this->add_field(
			'cache_ttl',
			esc_html__( 'Cache lifetime (seconds)', 'wp-inline-css-optimizer' ),
			'number',
			'optimization',
			array(
				'description' => esc_html__( 'How long a minified result is cached. Results are also invalidated automatically when the plugin is updated.', 'wp-inline-css-optimizer' ),
				'min'         => 0,
				'max'         => 2592000,
				'priority'    => 40,
			)
		);

		$this->add_section(
			'winden',
			esc_html__( 'Winden (Tailwind)', 'wp-inline-css-optimizer' ),
			'general',
			esc_html__( 'Optional integration with the Winden plugin. Inert when Winden is not active.', 'wp-inline-css-optimizer' ),
			array( 'priority' => 20 )
		);

		$this->add_field(
			'winden_fast_path',
			esc_html__( 'Optimize Winden compiled CSS', 'wp-inline-css-optimizer' ),
			'checkbox',
			'winden',
			array(
				'description' => esc_html__( 'Target the winden-compiled-css handle explicitly when it is present.', 'wp-inline-css-optimizer' ),
			)
		);

		$this->add_field(
			'winden_file_minify',
			esc_html__( 'Minify compiled output.css on save', 'wp-inline-css-optimizer' ),
			'checkbox',
			'winden',
			array(
				'description' => esc_html__( 'Rewrite uploads/winden/output.css when Winden regenerates it. Affects both inline and external-file modes.', 'wp-inline-css-optimizer' ),
				'priority'    => 20,
			)
		);

		$this->add_section(
			'advanced',
			esc_html__( 'Advanced', 'wp-inline-css-optimizer' ),
			'general',
			'',
			array( 'priority' => 30 )
		);

		$this->add_field(
			'included_handles',
			esc_html__( 'Only these handles', 'wp-inline-css-optimizer' ),
			'textarea',
			'advanced',
			array(
				'description' => esc_html__( 'One stylesheet handle per line. When set, only these handles are minified. Leave empty to process all handles.', 'wp-inline-css-optimizer' ),
				'rows'        => 4,
			)
		);

		$this->add_field(
			'excluded_handles',
			esc_html__( 'Exclude these handles', 'wp-inline-css-optimizer' ),
			'textarea',
			'advanced',
			array(
				'description' => esc_html__( 'One stylesheet handle per line. These handles are never minified.', 'wp-inline-css-optimizer' ),
				'rows'        => 4,
				'priority'    => 20,
			)
		);

		$this->add_field(
			'output_buffer_fallback',
			esc_html__( 'Head output-buffer fallback', 'wp-inline-css-optimizer' ),
			'checkbox',
			'advanced',
			array(
				'description' => esc_html__( 'Also minify raw <style> tags printed directly into the head. Requires WordPress 6.5+. Off by default; may conflict with other buffering plugins.', 'wp-inline-css-optimizer' ),
				'priority'    => 30,
			)
		);

		$this->add_field(
			'debug',
			esc_html__( 'Developer mode', 'wp-inline-css-optimizer' ),
			'checkbox',
			'advanced',
			array(
				'description' => esc_html__( 'Print a per-handle report of what this plugin did to the current page as an HTML comment at the end of the page (view source). Only logged-in administrators see it. Leave off in production.', 'wp-inline-css-optimizer' ),
				'priority'    => 40,
			)
		);

		$this->add_field(
			'debug_log',
			esc_html__( 'Developer mode: log to debug.log', 'wp-inline-css-optimizer' ),
			'checkbox',
			'advanced',
			array(
				'description' => esc_html__( 'Write every step of the optimization to the WordPress debug log (requires WP_DEBUG_LOG). Includes any fatal error and the final report, so you can see what happened even if the page fails to render. Leave off in production.', 'wp-inline-css-optimizer' ),
				'priority'    => 50,
			)
		);

		$this->add_field(
			'debug_suggestions',
			esc_html__( 'Developer mode: performance suggestions', 'wp-inline-css-optimizer' ),
			'checkbox',
			'advanced',
			array(
				'description' => esc_html__( 'Add an advisory list of low-risk performance opportunities (resource hints, font preloads, unminified inline CSS) to the Developer mode report. Read-only: nothing on the page is changed.', 'wp-inline-css-optimizer' ),
				'priority'    => 60,
			)
		);
	}

	public function get_shared_settings_path(): string {
		return WP_INLINE_CSS_OPTIMIZER_URL . 'includes/shared/shared-settings/';
	}

	public function enqueue_assets( string $hook ): void {
		if ( "settings_page_{$this->menu_slug}" !== $hook ) {
			return;
		}

		$shared_url = $this->get_shared_settings_path();

		wp_register_script( 'shared-settings', $shared_url . 'shared-settings.js', array(), '0.3.0', true );
		wp_register_style( 'shared-settings', $shared_url . 'shared-settings.css', array(), '0.3.0' );

		wp_enqueue_script( 'shared-settings' );
		wp_enqueue_style( 'shared-settings' );

		wp_localize_script(
			'shared-settings',
			'SharedSettingsConfig',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'shared_settings_save' ),
				'optionName'    => $this->option_name,
				'settingsGroup' => $this->settings_group,
				'saveAction'    => $this->option_name . '_save',
				'resetAction'   => $this->option_name . '_reset',
			)
		);
	}

	/**
	 * Flush the CSS cache whenever settings change (handles or toggles).
	 */
	public function on_option_updated(): void {
		\wp_inline_css_optimizer_flush_css_cache();
	}

	protected function get_defaults(): array {
		return \wp_inline_css_optimizer_default_options();
	}

	protected function get_plugin_dir(): string {
		return WP_INLINE_CSS_OPTIMIZER_DIR;
	}

	protected function get_plugin_url(): string {
		return WP_INLINE_CSS_OPTIMIZER_URL;
	}

	public function sanitize( $input ): array {
		$defaults = \wp_inline_css_optimizer_default_options();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$out = $defaults;

		$bool_keys = array(
			'enabled',
			'aggressive',
			'winden_fast_path',
			'winden_file_minify',
			'output_buffer_fallback',
			'debug',
			'debug_log',
			'debug_suggestions',
		);

		foreach ( $bool_keys as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$out['min_bytes'] = isset( $input['min_bytes'] ) ? absint( $input['min_bytes'] ) : $defaults['min_bytes'];
		$out['min_bytes'] = min( 1000000, $out['min_bytes'] );

		$out['cache_ttl'] = isset( $input['cache_ttl'] ) ? absint( $input['cache_ttl'] ) : $defaults['cache_ttl'];
		$out['cache_ttl'] = min( 2592000, $out['cache_ttl'] );

		$included = isset( $input['included_handles'] ) ? sanitize_textarea_field( (string) $input['included_handles'] ) : '';
		$excluded = isset( $input['excluded_handles'] ) ? sanitize_textarea_field( (string) $input['excluded_handles'] ) : '';

		$out['included_handles'] = implode( "\n", \wp_inline_css_optimizer_parse_handle_list( $included ) );
		$out['excluded_handles'] = implode( "\n", \wp_inline_css_optimizer_parse_handle_list( $excluded ) );

		return $out;
	}
}
