<?php
/**
 * Shared form field renderers for the settings table layout.
 *
 * @package WpPluginShared
 * @since   0.2.0
 */

namespace WpPluginShared;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Fields {

	/**
	 * Render a field into the settings table.
	 *
	 * @param array  $field   Field configuration.
	 * @param Store  $store   Options store.
	 * @param string $form_id Form id prefix for element ids.
	 */
	public static function render( array $field, Store $store, string $form_id ): void {
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
			case 'select':
				self::select( $field, $store, $form_id );
				break;
			case 'media':
				self::media( $field, $store, $form_id );
				break;
			default:
				do_action( 'ss_render_field_' . $type, $field, $store, $form_id );
				break;
		}
	}

	public static function text( array $field, Store $store, string $form_id ): void {
		$key   = $field['id'];
		$value = $store->get( $key, '' );
		$input_id = $form_id . '-' . $key;

		printf(
			'<input type="text" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" placeholder="%5$s" />',
			esc_attr( $input_id ),
			esc_attr( $field['option_name'] ?? '' ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $field['placeholder'] ?? '' )
		);
	}

	public static function textarea( array $field, Store $store, string $form_id ): void {
		$key   = $field['id'];
		$value = $store->get( $key, '' );
		$rows  = $field['rows'] ?? 4;
		$input_id = $form_id . '-' . $key;

		printf(
			'<textarea id="%1$s" name="%2$s[%3$s]" rows="%4$d" class="large-text" placeholder="%5$s">%6$s</textarea>',
			esc_attr( $input_id ),
			esc_attr( $field['option_name'] ?? '' ),
			esc_attr( $key ),
			(int) $rows,
			esc_attr( $field['placeholder'] ?? '' ),
			esc_textarea( $value )
		);
	}

	public static function number( array $field, Store $store, string $form_id ): void {
		$key   = $field['id'];
		$value = (int) $store->get( $key, 0 );
		$min   = $field['min'] ?? 0;
		$max   = $field['max'] ?? 10000;
		$input_id = $form_id . '-' . $key;

		printf(
			'<input type="number" id="%1$s" name="%2$s[%3$s]" value="%4$d" min="%5$d" max="%6$d" class="small-text" />',
			esc_attr( $input_id ),
			esc_attr( $field['option_name'] ?? '' ),
			esc_attr( $key ),
			$value,
			(int) $min,
			(int) $max
		);
	}

	public static function checkbox( array $field, Store $store, string $form_id ): void {
		$key     = $field['id'];
		$checked = $store->get( $key, false );
		$input_id = $form_id . '-' . $key;

		echo '<div class="wp-polyfill-setting-wrapper">';
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1" %4$s />',
			esc_attr( $input_id ),
			esc_attr( $field['option_name'] ?? '' ),
			esc_attr( $key ),
			checked( $checked, true, false )
		);
		printf(
			'<label for="%1$s">%2$s</label>',
			esc_attr( $input_id ),
			esc_html( $field['label'] )
		);
		echo '</div>';
	}

	public static function checkbox_group( array $field, Store $store, string $form_id ): void {
		$options = $field['options'] ?? [];
		$values  = (array) $store->get( $field['id'], [] );
		$name    = ( $field['option_name'] ?? '' ) . '[' . $field['id'] . '][]';
		$layout  = $field['options_layout'] ?? 'vertical';
		$wrapper_class = 'vertical' === $layout ? '' : ' wp-polyfill-checkbox-group--horizontal';

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

	public static function select( array $field, Store $store, string $form_id ): void {
		$key     = $field['id'];
		$value   = $store->get( $key, '' );
		$options = $field['options'] ?? [];
		$input_id = $form_id . '-' . $key;

		// Support callable options (lazy-loaded).
		if ( is_callable( $options ) ) {
			$options = $options();
		}

		printf(
			'<select id="%1$s" name="%2$s[%3$s]" class="regular-text">',
			esc_attr( $input_id ),
			esc_attr( $field['option_name'] ?? '' ),
			esc_attr( $key )
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

	public static function media( array $field, Store $store, string $form_id ): void {
		$key      = $field['id'];
		$id       = (int) $store->get( $key, 0 );
		$name     = ( $field['option_name'] ?? '' ) . '[' . $key . ']';
		$input_id = $form_id . '-' . $key;

		printf(
			'<input type="hidden" id="%1$s" name="%2$s" value="%3$d" />',
			esc_attr( $input_id ),
			esc_attr( $name ),
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
}
