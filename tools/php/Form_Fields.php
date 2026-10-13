<?php
/**
 * Shared form field renderers for the settings table layout.
 *
 * @package WpPluginShared
 * @since   0.3.0
 */

namespace WpPluginShared;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Fields {

	/**
	 * Render a field into the settings table.
	 *
	 * @param array          $field   Field configuration.
	 * @param Settings_Store $store   Options store.
	 * @param string         $form_id Form id prefix for element ids.
	 */
	public static function render( array $field, Settings_Store $store, string $form_id ): void {
		$type = $field['type'] ?? 'text';

		switch ( $type ) {
			case 'text':
				self::text( $field, $store, $form_id );
				break;
			case 'textarea':
				self::textarea( $field, $store, $form_id );
				break;
			case 'number':
				self::number( $field, $store, $form_id );
				break;
			case 'checkbox':
				self::checkbox( $field, $store, $form_id );
				break;
			case 'checkbox_group':
				self::checkbox_group( $field, $store, $form_id );
				break;
			case 'checkbox_map':
				self::checkbox_map( $field, $store, $form_id );
				break;
			case 'select':
				self::select( $field, $store, $form_id );
				break;
			case 'media':
				self::media( $field, $store, $form_id );
				break;
			case 'context':
				self::context( $field, $store, $form_id );
				break;
			case 'repeater':
				self::repeater( $field, $store, $form_id );
				break;
			default:
				do_action( 'wp_polyfill_render_field_' . $type, $field, $store, $form_id );
				break;
		}
	}

	/**
	 * Build the HTML field name.
	 *
	 * A `name_path` (e.g. `post_type_noindex[product]`) emits a nested option
	 * key: `{option_name}[post_type_noindex][product]`. Without it the field id
	 * is used: `{option_name}[{id}]`.
	 */
	public static function field_name( array $field ): string {
		$base = $field['option_name'] ?? '';
		$path = ! empty( $field['name_path'] ) ? $field['name_path'] : ( $field['id'] ?? '' );

		return $base ? "{$base}[{$path}]" : $path;
	}

	/**
	 * Build a stable, unique input id for a field.
	 */
	public static function field_id( array $field, string $form_id ): string {
		if ( ! empty( $field['compute_id'] ) ) {
			return $form_id . '-' . $field['id'];
		}

		$key = ! empty( $field['name_path'] ) ? $field['name_path'] : ( $field['id'] ?? '' );

		return $form_id . '-' . preg_replace( '/[^a-z0-9_-]+/i', '-', $key );
	}

	/**
	 * Resolve the stored value for a field, walking `name_path` maps when present.
	 */
	public static function field_value( array $field, Settings_Store $store, $default = '' ) {
		$fallback = array_key_exists( 'default', $field ) ? $field['default'] : $default;

		if ( empty( $field['name_path'] ) ) {
			return $store->get( $field['id'] ?? '', $fallback );
		}

		$segments = explode( '[', $field['name_path'] );
		$top      = array_shift( $segments );
		$value    = $store->get( (string) $top, [] );

		if ( ! is_array( $value ) ) {
			return $fallback;
		}

		foreach ( $segments as $segment ) {
			$segment = rtrim( $segment, ']' );
			if ( ! isset( $value[ $segment ] ) ) {
				return $fallback;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	public static function text( array $field, Settings_Store $store, string $form_id ): void {
		$value       = self::field_value( $field, $store, '' );
		$input_id    = self::field_id( $field, $form_id );
		$placeholder = self::placeholder( $field, $store );

		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="%4$s" />',
			esc_attr( $input_id ),
			esc_attr( self::field_name( $field ) ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	public static function textarea( array $field, Settings_Store $store, string $form_id ): void {
		$value       = self::field_value( $field, $store, '' );
		$rows        = $field['rows'] ?? 4;
		$input_id    = self::field_id( $field, $form_id );
		$placeholder = self::placeholder( $field, $store );

		printf(
			'<textarea id="%1$s" name="%2$s" rows="%3$d" class="large-text" placeholder="%4$s">%5$s</textarea>',
			esc_attr( $input_id ),
			esc_attr( self::field_name( $field ) ),
			(int) $rows,
			esc_attr( $placeholder ),
			esc_textarea( $value )
		);
	}

	public static function number( array $field, Settings_Store $store, string $form_id ): void {
		$value    = (int) self::field_value( $field, $store, 0 );
		$min      = $field['min'] ?? 0;
		$max      = $field['max'] ?? 10000;
		$input_id = self::field_id( $field, $form_id );

		printf(
			'<input type="number" id="%1$s" name="%2$s" value="%3$d" min="%4$d" max="%5$d" class="small-text" />',
			esc_attr( $input_id ),
			esc_attr( self::field_name( $field ) ),
			$value,
			(int) $min,
			(int) $max
		);
	}

	public static function checkbox( array $field, Settings_Store $store, string $form_id ): void {
		$checked  = self::field_value( $field, $store, false );
		$input_id = self::field_id( $field, $form_id );

		echo '<div class="wp-polyfill-setting-wrapper">';
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
			esc_attr( $input_id ),
			esc_attr( self::field_name( $field ) ),
			checked( $checked, true, false )
		);
		printf(
			'<label for="%1$s">%2$s</label>',
			esc_attr( $input_id ),
			esc_html( $field['label'] )
		);
		echo '</div>';
	}

	public static function checkbox_group( array $field, Settings_Store $store, string $form_id ): void {
		$options = $field['options'] ?? [];
		$values  = (array) $store->get( $field['id'], [] );
		$name    = ( $field['option_name'] ?? '' ) . '[' . $field['id'] . '][]';
		$layout  = $field['options_layout'] ?? 'vertical';
		$wrapper_class = 'vertical' === $layout ? '' : ' wp-polyfill-checkbox-group--horizontal';

		if ( is_callable( $options ) ) {
			$options = $options();
		}

		echo '<div class="wp-polyfill-checkbox-group' . esc_attr( $wrapper_class ) . '">';

		foreach ( $options as $value => $label ) {
			$input_id = $form_id . '-' . $field['id'] . '-' . sanitize_html_class( $value );
			$checked  = in_array( (string) $value, array_map( 'strtolower', $values ), true );

			echo '<div class="wp-polyfill-setting-wrapper">';
			printf(
				'<input type="checkbox" id="%1$s" name="%2$s" value="%3$s" %4$s />',
				esc_attr( $input_id ),
				esc_attr( $name ),
				esc_attr( $value ),
				$checked ? ' checked' : ''
			);
			printf(
				'<label for="%1$s">%2$s</label>',
				esc_attr( $input_id ),
				esc_html( $label )
			);
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render a boolean map: one checkbox per option key, each stored as its own
	 * nested key (`{option_name}[{id}][{key}]` → 0/1). Unchecked keys are simply
	 * absent from the submitted form data.
	 */
	public static function checkbox_map( array $field, Settings_Store $store, string $form_id ): void {
		$options = $field['options'] ?? [];
		$values  = (array) self::field_value( $field, $store, [] );
		$layout  = $field['options_layout'] ?? 'vertical';
		$wrapper_class = 'vertical' === $layout ? '' : ' wp-polyfill-checkbox-group--horizontal';

		if ( is_callable( $options ) ) {
			$options = $options();
		}

		echo '<div class="wp-polyfill-checkbox-group' . esc_attr( $wrapper_class ) . '">';

		foreach ( $options as $key => $label ) {
			$member = array_merge( $field, array( 'name_path' => $field['id'] . '[' . $key . ']' ) );
			$input_id = self::field_id( $member, $form_id );
			$checked  = ! empty( $values[ $key ] );

			echo '<div class="wp-polyfill-setting-wrapper">';
			printf(
				'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
				esc_attr( $input_id ),
				esc_attr( self::field_name( $member ) ),
				$checked ? ' checked' : ''
			);
			printf(
				'<label for="%1$s">%2$s</label>',
				esc_attr( $input_id ),
				esc_html( $label )
			);
			echo '</div>';
		}

		echo '</div>';
	}

	public static function select( array $field, Settings_Store $store, string $form_id ): void {
		$value    = self::field_value( $field, $store, '' );
		$options  = $field['options'] ?? [];
		$input_id = self::field_id( $field, $form_id );

		if ( is_callable( $options ) ) {
			$options = $options();
		}

		printf(
			'<select id="%1$s" name="%2$s" class="regular-text">',
			esc_attr( $input_id ),
			esc_attr( self::field_name( $field ) )
		);

		foreach ( $options as $opt_value => $opt_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $opt_value ),
				selected( (string) $value, (string) $opt_value, false ),
				esc_html( $opt_label )
			);
		}

		echo '</select>';
	}

	public static function media( array $field, Settings_Store $store, string $form_id ): void {
		$id       = (int) self::field_value( $field, $store, 0 );
		$input_id = self::field_id( $field, $form_id );

		printf(
			'<input type="hidden" id="%1$s" name="%2$s" value="%3$d" />',
			esc_attr( $input_id ),
			esc_attr( self::field_name( $field ) ),
			$id
		);

		echo '<div class="wp-polyfill-media-preview" data-target="' . esc_attr( $input_id ) . '">';

		if ( $id ) {
			if ( wp_attachment_is_image( $id ) ) {
				echo wp_get_attachment_image( $id, 'thumbnail', false, [
					'style' => 'max-width:200px;height:auto;display:block;margin:6px 0;',
				] );
			} elseif ( wp_get_attachment_url( $id ) ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( wp_get_attachment_url( $id ) ),
					sprintf( esc_html__( 'Attachment #%d', 'shared-settings' ), $id )
				);
			}
		}

		echo '</div>';

		printf(
			'<button type="button" class="button wp-polyfill-pick-media" data-target="%s">%s</button>',
			esc_attr( $input_id ),
			esc_html__( 'Select media', 'shared-settings' )
		);
	}

	/**
	 * Render a composite settings row: one control group per site context.
	 *
	 * Each row holds an optional noindex toggle plus title and description
	 * templates and an Open Graph image. All controls map to their own option
	 * keys via `name_path`, so the regular serializer and sanitize round-trip
	 * unchanged. The parent field's `name_path` should be the title control's
	 * path so the row label binds to the title input.
	 *
	 * @param array $field Field config: { 'context' => array } with keys
	 *                     'title'|'description'|'og_image' (option keys) and
	 *                     optional 'noindex' (option key) + 'member' (map key).
	 */
	public static function context( array $field, Settings_Store $store, string $form_id ): void {
		$spec = $field['context'] ?? [];
		$map  = (string) ( $spec['member'] ?? '' );

		echo '<div class="wp-polyfill-context-fields">';

		if ( ! empty( $spec['noindex'] ) ) {
			$sub      = self::context_field( $field, $spec['noindex'], $map );
			$input_id = self::field_id( $sub, $form_id );
			$checked  = (bool) self::field_value( $sub, $store, false );

			echo '<div class="wp-polyfill-context-control wp-polyfill-context-control--inline">';
			printf(
				'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
				esc_attr( $input_id ),
				esc_attr( self::field_name( $sub ) ),
				$checked ? ' checked' : ''
			);
			printf(
				'<label for="%1$s">%2$s</label>',
				esc_attr( $input_id ),
				esc_html__( 'Noindex', 'shared-settings' )
			);
			echo '</div>';
		}

		if ( ! empty( $spec['title'] ) ) {
			$sub = self::context_field( $field, $spec['title'], $map );

			echo '<div class="wp-polyfill-context-control">';
			printf(
				'<span class="wp-polyfill-context-control__label">%s</span>',
				esc_html__( 'Title template', 'shared-settings' )
			);
			self::text( $sub, $store, $form_id );
			echo '</div>';
		}

		if ( ! empty( $spec['description'] ) ) {
			$sub = self::context_field( $field, $spec['description'], $map );

			echo '<div class="wp-polyfill-context-control">';
			printf(
				'<span class="wp-polyfill-context-control__label">%s</span>',
				esc_html__( 'Description template', 'shared-settings' )
			);
			self::textarea( $sub, $store, $form_id );
			echo '</div>';
		}

		if ( ! empty( $spec['og_image'] ) ) {
			$sub = self::context_field( $field, $spec['og_image'], $map );

			echo '<div class="wp-polyfill-context-control">';
			printf(
				'<span class="wp-polyfill-context-control__label">%s</span>',
				esc_html__( 'Open Graph image', 'shared-settings' )
			);
			self::media( $sub, $store, $form_id );
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Build a sub-field descriptor for one control of a context row.
	 */
	private static function context_field( array $field, string $key, string $map = '' ): array {
		$name_path = $map ? "{$key}[{$map}]" : $key;

		return array(
			'id'          => $field['id'],
			'name_path'   => $name_path,
			'option_name' => $field['option_name'] ?? '',
			'rows'        => $field['rows'] ?? 2,
		);
	}

	/**
	 * Render a dynamic repeater: zero or more rows the user can add to and
	 * remove from. Each row renders the controls described by `$field['repeater']`
	 * under the option path `{field_id}[{index}][{control_name}]`. A blank row
	 * is emitted as a `<template>` and cloned client-side on "add".
	 *
	 * @param array $field Field config:
	 *   'repeater' => array(
	 *     'add_button'      => string
	 *     'row_label'       => string
	 *     'context_options' => list of { value, label, group }
	 *     'fields'          => list of { name, type, label, rows?, placeholder? }
	 *                        (the array key may carry the name when not given)
	 *   )
	 *   Field types: 'contexts' (multi-select combobox), 'text', 'textarea',
	 *   'checkbox', 'media'.
	 */
	public static function repeater( array $field, Settings_Store $store, string $form_id ): void {
		$schema = $field['repeater'] ?? array();
		$rows   = self::field_value( $field, $store, array() );

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		echo '<div class="wp-polyfill-repeater" data-repeater>';
		echo '<div class="wp-polyfill-repeater__rows" data-repeater-rows>';

		foreach ( array_values( $rows ) as $index => $row ) {
			self::render_repeater_row( $field, $store, $form_id, (int) $index, is_array( $row ) ? $row : array(), $schema );
		}

		echo '</div>';

		// Blank row template for the JS "add" button.
		echo '<template data-repeater-template>';
		self::render_repeater_row( $field, $store, $form_id, '__INDEX__', array(), $schema );
		echo '</template>';

		printf(
			'<button type="button" class="button wp-polyfill-repeater__add" data-repeater-add>%s</button>',
			esc_html( $schema['add_button'] ?? __( 'Add override', 'shared-settings' ) )
		);

		echo '</div>';
	}

	/**
	 * Render a single repeater row (used for both stored rows and templates).
	 */
	private static function render_repeater_row( array $field, Settings_Store $store, string $form_id, $index, array $row, array $schema ): void {
		echo '<div class="wp-polyfill-repeater__row" data-repeater-row data-index="' . esc_attr( (string) $index ) . '">';
		echo '<div class="wp-polyfill-repeater__row-head">';
		printf(
			'<span class="wp-polyfill-repeater__row-title">%s</span>',
			esc_html( $schema['row_label'] ?? __( 'Override', 'shared-settings' ) )
		);
		printf(
			'<button type="button" class="button-link wp-polyfill-repeater__remove" data-repeater-remove aria-label="%s">&times;</button>',
			esc_attr__( 'Remove this override', 'shared-settings' )
		);
		echo '</div>';
		echo '<div class="wp-polyfill-repeater__row-body">';

		foreach ( ( $schema['fields'] ?? array() ) as $name => $control ) {
			if ( ! is_array( $control ) ) {
				continue;
			}

			if ( '' === ( $control['name'] ?? '' ) && is_string( $name ) ) {
				$control['name'] = $name;
			}

			self::render_repeater_control( $field, $store, $form_id, $index, $row, $control, $schema );
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render one control of a repeater row.
	 */
	private static function render_repeater_control( array $field, Settings_Store $store, string $form_id, $index, array $row, array $control, array $schema ): void {
		$type   = $control['type'] ?? 'text';
		$name   = (string) ( $control['name'] ?? '' );

		if ( '' === $name ) {
			return;
		}

		$sub = array(
			'id'          => ( $field['id'] ?? '' ) . '-' . $index . '-' . $name,
			'name_path'   => ( $field['id'] ?? '' ) . '[' . $index . '][' . $name . ']',
			'option_name' => $field['option_name'] ?? '',
			'label'       => $control['label'] ?? '',
			'rows'        => $control['rows'] ?? 2,
			'placeholder' => $control['placeholder'] ?? '',
			// field_id() prefers name_path (a bracketed path yields a trailing
			// dash); force the clean explicit id we constructed above instead.
			'compute_id'  => true,
		);

		echo '<div class="wp-polyfill-repeater__field wp-polyfill-repeater__field--' . esc_attr( $type ) . '">';

		if ( ! empty( $control['label'] ) && 'checkbox' !== $type ) {
			printf(
				'<span class="wp-polyfill-repeater__field-label">%s</span>',
				esc_html( $control['label'] )
			);
		}

		switch ( $type ) {
			case 'contexts':
				self::contexts_control( $sub, $row['contexts'] ?? array(), $schema['context_options'] ?? array() );
				break;
			case 'checkbox':
				self::checkbox( $sub, $store, $form_id );
				break;
			case 'media':
				self::media( $sub, $store, $form_id );
				break;
			case 'textarea':
				self::textarea( $sub, $store, $form_id );
				break;
			default:
				self::text( $sub, $store, $form_id );
				break;
		}

		echo '</div>';
	}

	/**
	 * Render the multi-select context combobox for a repeater row.
	 *
	 * The visible chips + dropdown are enhanced by shared-settings.js. State
	 * lives in hidden checkboxes named `{option_name}[{field}][{index}][contexts][]`
	 * so the existing FormData serializer and sanitize round-trip unchanged.
	 */
	private static function contexts_control( array $sub, $selected, array $options ): void {
		$selected = is_array( $selected ) ? $selected : array();
		$base_name = self::field_name( $sub ) . '[]';

		echo '<div class="wp-polyfill-combobox" data-context-combobox>';
		echo '<div class="wp-polyfill-combobox__chips">';
		echo '<ul class="wp-polyfill-combobox__chip-list"></ul>';
		echo '<input type="text" class="wp-polyfill-combobox__search" placeholder="' . esc_attr__( 'Type to add a context…', 'shared-settings' ) . '" autocomplete="off" />';
		echo '</div>';
		echo '<div class="wp-polyfill-combobox__menu" hidden></div>';
		echo '<div class="wp-polyfill-combobox__values" data-name="' . esc_attr( $base_name ) . '" hidden>';

		foreach ( $options as $opt ) {
			if ( in_array( (string) $opt['value'], $selected, true ) ) {
				printf(
					'<input type="checkbox" name="%1$s" value="%2$s" checked />',
					esc_attr( $base_name ),
					esc_attr( (string) $opt['value'] )
				);
			}
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Placeholder for text-ish inputs: an explicit placeholder wins, otherwise
	 * any non-empty default value for the field acts as the hint.
	 */
	private static function placeholder( array $field, Settings_Store $store ): string {
		$placeholder = (string) ( $field['placeholder'] ?? '' );

		if ( '' === $placeholder ) {
			$key     = (string) ( $field['name_path'] ?? $field['id'] ?? '' );
			$default = $store->defaults();

			if ( $key && isset( $default[ $key ] ) && '' !== (string) $default[ $key ] ) {
				$placeholder = (string) $default[ $key ];
			}
		}

		return $placeholder;
	}
}