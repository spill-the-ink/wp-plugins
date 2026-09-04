<?php
/**
 * Shared settings page with Bricks-style tabs, tables, search and AJAX save.
 *
 * Subclass this in each plugin, call parent::__construct(), then add tabs
 * and fields before the admin_init hook fires.
 *
 * @package WpPluginShared
 * @since   0.2.0
 */

namespace WpPluginShared;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Page {

	protected string $menu_slug      = '';
	protected string $page_title    = '';
	protected string $menu_title    = '';
	protected string $option_name   = '';
	protected string $settings_group = '';
	protected string $form_id       = 'shared-settings';

	private array  $tabs      = [];
	private array  $sections   = [];
	private array  $fields     = [];
	private ?Store $store      = null;

	public function __construct( array $args ) {
		$this->menu_slug      = $args['menu_slug']      ?? '';
		$this->page_title    = $args['page_title']      ?? '';
		$this->menu_title    = $args['menu_title']      ?? '';
		$this->option_name   = $args['option_name']      ?? '';
		$this->settings_group = $args['settings_group'] ?? '';
		$this->form_id       = $args['form_id']         ?? $this->menu_slug . '-settings';

		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// AJAX handlers must be registered on every request (not just the settings page)
		add_action( 'wp_ajax_' . $this->option_name . '_save', [ $this, 'ajax_save' ] );
		add_action( 'wp_ajax_' . $this->option_name . '_reset', [ $this, 'ajax_reset' ] );

		$this->store = new Store( $this->option_name, $this->get_defaults() );
	}

	/**
	 * Register the settings page under Settings > {menu_title}.
	 */
	public function register_menu(): void {
		add_options_page(
			$this->page_title,
			$this->menu_title,
			'manage_options',
			$this->menu_slug,
			[ $this, 'render' ]
		);
	}

	/**
	 * Register the option with WordPress Settings API.
	 */
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

	/**
	 * Enqueue shared settings JS and CSS on the settings page.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( "settings_page_{$this->menu_slug}" !== $hook ) {
			return;
		}

		$plugin_dir = $this->get_plugin_dir();
		$plugin_url = $this->get_plugin_url();

		wp_register_script(
			'shared-settings',
			$plugin_url . 'shared-settings.js',
			[],
			'0.2.0',
			true
		);

		wp_register_style(
			'shared-settings',
			$plugin_url . 'shared-settings.css',
			[],
			'0.2.0'
		);

		wp_enqueue_script( 'shared-settings' );
		wp_enqueue_style( 'shared-settings' );

		wp_localize_script( 'shared-settings', 'SharedSettingsConfig', [
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'shared_settings_save' ),
			'optionName'   => $this->option_name,
			'settingsGroup' => $this->settings_group,
			'saveAction'   => $this->option_name . '_save',
			'resetAction'  => $this->option_name . '_reset',
		] );
	}

	/**
	 * AJAX handler: save settings.
	 */
	public function ajax_save(): void {
		check_ajax_referer( 'shared_settings_save', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$raw  = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
		$json = json_decode( is_string( $raw ) ? $raw : wp_json_encode( $raw ), true );
		$clean = $this->sanitize( is_array( $json ) ? $json : [] );

		update_option( $this->option_name, $clean );
		$this->store->reset();

		wp_send_json_success( [
			'message' => __( 'Settings saved.', 'shared-settings' ),
		] );
	}

	/**
	 * AJAX handler: reset settings to defaults.
	 */
	public function ajax_reset(): void {
		check_ajax_referer( 'shared_settings_save', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		update_option( $this->option_name, $this->get_defaults() );
		$this->store->reset();

		wp_send_json_success( [
			'message' => __( 'Settings reset to defaults.', 'shared-settings' ),
			'defaults' => $this->get_defaults(),
		] );
	}

	/**
	 * Sanitize input. Override in subclass for custom sanitization.
	 */
	public function sanitize( $input ): array {
		return is_array( $input ) ? array_map( 'sanitize_text_field', $input ) : $this->get_defaults();
	}

	// ──────────────────────────────────────────────
	//  Registration API (call from subclass)
	// ──────────────────────────────────────────────

	/**
	 * Register a settings tab.
	 *
	 * @param string $id       Tab identifier (used as table id: tab-{id}).
	 * @param string $label    Tab label displayed in nav.
	 * @param string $icon     Dashicons class (e.g. 'dashicons-admin-generic').
	 * @param array  $args     Optional: { 'priority' => int }.
	 */
	protected function add_tab( string $id, string $label, string $icon = '', array $args = [] ): void {
		$this->tabs[ $id ] = [
			'id'       => $id,
			'label'    => $label,
			'icon'     => $icon,
			'priority' => $args['priority'] ?? 10,
		];
	}

	/**
	 * Register a settings section within a tab.
	 *
	 * @param string $id      Section identifier.
	 * @param string $title   Section heading.
	 * @param string $tab_id  Parent tab id.
	 * @param string $description  Optional section description.
	 * @param array  $args    Optional: { 'priority' => int }.
	 */
	protected function add_section( string $id, string $title, string $tab_id, string $description = '', array $args = [] ): void {
		$this->sections[ $id ] = [
			'id'          => $id,
			'title'       => $title,
			'tab_id'      => $tab_id,
			'description' => $description,
			'priority'    => $args['priority'] ?? 10,
		];
	}

	/**
	 * Register a field within a section.
	 *
	 * @param string $id         Field identifier (becomes option key).
	 * @param string $label      Field label.
	 * @param string $type       Field type: text|textarea|number|checkbox|select|media|heading|html|checkbox_group.
	 * @param string $section_id Parent section id.
	 * @param array  $args       Type-specific options:
	 *   'description'  => string
	 *   'placeholder'  => string
	 *   'default'      => mixed
	 *   'min' / 'max'  => int (number type)
	 *   'rows'         => int (textarea type)
	 *   'options'      => array|callable (select type)
	 *   'show_if'      => string  e.g. '#otherField' or '{ "selector": "#field", "value": "val" }'
	 *   'show_if_not'  => string  inverse of show_if
	 *   'show_value'   => string  value to match (used with show_if/show_if_not)
	 *   'disable_when_hidden' => bool
	 *   'html'         => string  raw HTML for html type
	 *   'heading_tag'  => string  h2|h3|h4 for heading type
	 *   'options_layout' => 'horizontal' (for checkbox_group)
	 */
	protected function add_field( string $id, string $label, string $type, string $section_id, array $args = [] ): void {
		$this->fields[ $id ] = [
			'id'         => $id,
			'label'      => $label,
			'type'       => $type,
			'section_id' => $section_id,
			'description'      => $args['description'] ?? '',
			'placeholder'      => $args['placeholder'] ?? '',
			'default'          => $args['default'] ?? null,
			'min'              => $args['min'] ?? null,
			'max'              => $args['max'] ?? null,
			'rows'             => $args['rows'] ?? 4,
			'options'          => $args['options'] ?? [],
			'show_if'          => $args['show_if'] ?? '',
			'show_if_not'      => $args['show_if_not'] ?? '',
			'show_value'       => $args['show_value'] ?? '',
			'disable_when_hidden' => ! empty( $args['disable_when_hidden'] ),
			'html'             => $args['html'] ?? '',
			'heading_tag'      => $args['heading_tag'] ?? 'h3',
			'options_layout'   => $args['options_layout'] ?? 'vertical',
		];
	}

	// ──────────────────────────────────────────────
	//  Rendering
	// ──────────────────────────────────────────────

	/**
	 * Main render method. Outputs the complete settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->sort_registrations();
		$tabs     = $this->get_sorted_tabs();
		$first_id = $tabs ? $tabs[0]['id'] : '';

		echo '<div class="wrap ss-admin-wrapper ss-admin-settings-page">';
		$this->render_header();
		$this->render_search();
		$this->render_tabs( $tabs );

		echo '<form id="' . esc_attr( $this->form_id ) . '" class="ss-admin-wrapper" method="post" autocomplete="off">';

		foreach ( $tabs as $tab ) {
			$this->render_tab_table( $tab );
		}

		$this->render_submit_bar();
		echo '</form>';
		echo '</div>';
	}

	private function render_header(): void {
		echo '<div class="ss-settings-header">';
		echo '<div class="ss-settings-heading">';
		echo '<div class="ss-settings-title-row">';
		echo '<h1 class="title">' . esc_html( $this->page_title ) . '</h1>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	private function render_search(): void {
		echo '<div class="ss-settings-tab-search">';
		echo '<label class="screen-reader-text" for="ss-settings-tab-filter">' . esc_html__( 'Search settings', 'shared-settings' ) . '</label>';
		echo '<span class="dashicons dashicons-search" aria-hidden="true"></span>';
		echo '<input type="search" id="ss-settings-tab-filter" placeholder="' . esc_attr__( 'Search settings...', 'shared-settings' ) . '" autocomplete="off">';
		echo '<button type="button" class="ss-settings-search-clear" aria-label="' . esc_attr__( 'Clear search', 'shared-settings' ) . '" hidden>';
		echo '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
		echo '</button>';
		echo '</div>';
		echo '<p class="ss-settings-search-no-results" aria-live="polite" hidden>' . esc_html__( 'No settings found.', 'shared-settings' ) . '</p>';
	}

	private function render_tabs( array $tabs ): void {
		echo '<ul id="ss-settings-tabs-wrapper" class="nav-tab-wrapper">';

		foreach ( $tabs as $i => $tab ) {
			$active = 0 === $i ? ' nav-tab-active' : '';
			$aria  = 0 === $i ? ' aria-current="true"' : '';

			printf(
				'<li><a href="#%1$s" class="nav-tab%2$s"%3$s data-tab-id="%1$s">%4$s</a></li>',
				esc_attr( $tab['id'] ),
				$active,
				$aria,
				esc_html( $tab['label'] )
			);
		}

		echo '</ul>';
	}

	private function render_tab_table( array $tab ): void {
		$active = 0 === $this->tab_index( $tab['id'] ) ? ' active' : '';
		$tab_sections = $this->get_sections_for_tab( $tab['id'] );

		printf(
			'<table id="%1$s" class="ss-settings-table%2$s" role="presentation">',
			esc_attr( $tab['id'] ),
			$active
		);
		echo '<tbody>';

		foreach ( $tab_sections as $section ) {
			$this->render_section( $section );
		}

		echo '</tbody></table>';
	}

	private function render_section( array $section ): void {
		$section_fields = $this->get_fields_for_section( $section['id'] );

		// Section heading row: visually hidden, exposed to screen readers only.
		// The section title is a label for the field group, not a visible heading.
		if ( $section['title'] ) {
			echo '<tr class="ss-settings-section-row">';
			printf(
				'<th class="screen-reader-text" colspan="2" aria-label="%s"><span>%s</span></th></tr>',
				esc_attr( $section['title'] ),
				esc_html( $section['title'] )
			);
		}

		// Field rows.
		foreach ( $section_fields as $field ) {
			$this->render_field_row( $field );
		}
	}

	private function render_field_row( array $field ): void {
		$dep_attrs = $this->get_dependency_attrs( $field );
		$input_id  = esc_attr( $this->form_id . '-' . $field['id'] );

		if ( 'heading' === $field['type'] ) {
			$this->render_field_heading( $field );
			return;
		}

		if ( 'html' === $field['type'] ) {
			$this->render_field_html( $field );
			return;
		}

		printf(
			'<tr %s>',
			$dep_attrs
		);

		// Left column: label + description
		echo '<th>';
		printf( '<label for="%s">%s</label>', $input_id, esc_html( $field['label'] ) );
		if ( $field['description'] ) {
			printf( '<p class="description">%s</p>', wp_kses_post( $field['description'] ) );
		}
		echo '</th>';

		// Right column: control
		echo '<td>';
		Form_Fields::render( $field, $this->store, $this->form_id );
		echo '</td>';

		echo '</tr>';
	}

	private function render_field_heading( array $field ): void {
		$dep = $this->get_dependency_attrs( $field );

		printf( '<tr class="ss-settings-section-row" %s>', $dep );
		echo '<th>';
		printf( '<label>%s</label>', esc_html( $field['label'] ) );
		echo '</th><td></td></tr>';
	}

	private function render_field_html( array $field ): void {
		$dep = $this->get_dependency_attrs( $field );

		printf( '<tr class="ss-panel-row" %s><td colspan="2"><div class="ss-settings-panel">', $dep );
		echo $field['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></td></tr>';
	}

	private function render_submit_bar(): void {
		echo '<div class="ss-submit-wrapper">';
		printf(
			'<button type="submit" name="save" class="button button-primary button-large">%s</button>',
			esc_html__( 'Save Settings', 'shared-settings' )
		);
		echo '<p class="ss-settings-unsaved-message" role="status" aria-live="polite" hidden>';
		echo esc_html__( 'Unsaved changes', 'shared-settings' );
		echo '</p>';
		printf(
			'<button type="button" name="reset" class="button button-secondary button-large ss-reset-button">%s</button>',
			esc_html__( 'Reset Settings', 'shared-settings' )
		);
		echo '</div>';
		echo '<span class="spinner saving"></span>';
	}

	// ──────────────────────────────────────────────
	//  Dependency attributes
	// ──────────────────────────────────────────────

	private function get_dependency_attrs( array $field ): string {
		$attrs = [];

		if ( $field['show_if'] ) {
			$attrs[] = sprintf( 'data-settings-show-if="%s"', esc_attr( $field['show_if'] ) );
		}

		if ( $field['show_if_not'] ) {
			$attrs[] = sprintf( 'data-settings-show-if-not="%s"', esc_attr( $field['show_if_not'] ) );
		}

		if ( $field['show_value'] ) {
			$attrs[] = sprintf( 'data-settings-show-value="%s"', esc_attr( $field['show_value'] ) );
		}

		if ( $field['disable_when_hidden'] ) {
			$attrs[] = 'data-settings-disable-when-hidden="true"';
		}

		return $attrs ? implode( ' ', $attrs ) : '';
	}

	// ──────────────────────────────────────────────
	//  Helpers
	// ──────────────────────────────────────────────

	private function sort_registrations(): void {
		uasort( $this->tabs, fn( $a, $b ) => $a['priority'] - $b['priority'] );
		uasort( $this->sections, fn( $a, $b ) => $a['priority'] - $b['priority'] );
	}

	private function get_sorted_tabs(): array {
		return array_values( $this->tabs );
	}

	private function get_sections_for_tab( string $tab_id ): array {
		return array_values( array_filter(
			$this->sections,
			fn( $s ) => $s['tab_id'] === $tab_id
		) );
	}

	private function get_fields_for_section( string $section_id ): array {
		return array_values( array_filter(
			$this->fields,
			fn( $f ) => $f['section_id'] === $section_id
		) );
	}

	private function tab_index( string $tab_id ): int {
		$ids = array_keys( $this->tabs );
		$pos = array_search( $tab_id, $ids, true );

		return false !== $pos ? $pos : 0;
	}

	public function get_store(): Store {
		return $this->store;
	}

	/**
	 * Return the absolute filesystem directory of the calling plugin.
	 * Override in subclass if needed.
	 */
	protected function get_plugin_dir(): string {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 );
		foreach ( $backtrace as $frame ) {
			if ( isset( $frame['file'] ) && str_contains( $frame['file'], '/plugins/' ) ) {
				$parts = explode( '/plugins/', $frame['file'], 2 );
				if ( isset( $parts[1] ) ) {
					$plugin_rel = explode( '/', $parts[1] )[0];
					return plugin_dir_path( WP_PLUGIN_DIR . '/' . $plugin_rel );
				}
			}
		}
		return plugin_dir_path( __FILE__ );
	}

	/**
	 * Return the URL pointing to shared-settings assets in the calling plugin.
	 * Override in subclass.
	 */
	protected function get_plugin_url(): string {
		$dir = $this->get_plugin_dir();
		return plugin_dir_url( $dir . 'dummy.php' );
	}

	/**
	 * Return default option values. Override in subclass.
	 */
	protected function get_defaults(): array {
		return [];
	}
}
