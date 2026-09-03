<?php
/**
 * Shared form field renderers for the WordPress Settings API.
 *
 * @package WpPluginShared
 * @since   0.1.0
 */

namespace WpPluginShared;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Fields {

	public static function text( array $args, Settings_Store $store ): void {
		$key = $args['key'] ?? '';
		$val = $store->get( $key, '' );

		printf(
			'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />',
			esc_attr( $args['option_name'] ?? '' ),
			esc_attr( $key ),
			esc_attr( $val )
		);

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	public static function textarea( array $args, Settings_Store $store ): void {
		$key = $args['key'] ?? '';
		$val = $store->get( $key, '' );
		$rows = $args['rows'] ?? 4;

		printf(
			'<textarea class="large-text" rows="%1$d" name="%2$s[%3$s]">%4$s</textarea>',
			(int) $rows,
			esc_attr( $args['option_name'] ?? '' ),
			esc_attr( $key ),
			esc_textarea( $val )
		);

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	public static function number( array $args, Settings_Store $store ): void {
		$key  = $args['key'] ?? '';
		$val  = (int) $store->get( $key, 0 );
		$min  = $args['min'] ?? 0;
		$max  = $args['max'] ?? 1000;

		printf(
			'<input type="number" class="small-text" name="%1$s[%2$s]" value="%3$d" min="%4$d" max="%5$d" />',
			esc_attr( $args['option_name'] ?? '' ),
			esc_attr( $key ),
			$val,
			(int) $min,
			(int) $max
		);

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	public static function checkbox( array $args, Settings_Store $store ): void {
		$key     = $args['key'] ?? '';
		$checked = $store->get( $key, false );
		$label   = $args['label'] ?? '';

		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( $args['option_name'] ?? '' ),
			esc_attr( $key ),
			checked( $checked, true, false ),
			esc_html( $label )
		);

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	public static function media( array $args, Settings_Store $store ): void {
		$key      = $args['key'] ?? '';
		$id       = (int) $store->get( $key, 0 );
		$name     = ( $args['option_name'] ?? '' ) . '[' . $key . ']';
		$id_attr  = 'wps-media-' . $key;

		printf(
			'<input type="hidden" id="%1$s" name="%2$s" value="%3$d" />',
			esc_attr( $id_attr ),
			esc_attr( $name ),
			$id
		);

		echo '<div class="wps-media-preview" data-target="' . esc_attr( $id_attr ) . '">';

		if ( $id && wp_attachment_is_image( $id ) ) {
			echo wp_get_attachment_image( $id, 'thumbnail', false, [
				'style' => 'max-width:200px;height:auto;display:block;margin:6px 0;',
			] );
		} elseif ( $id && wp_get_attachment_url( $id ) ) {
			printf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( wp_get_attachment_url( $id ) ),
				sprintf( 'Attachment #%d', $id )
			);
		}

		echo '</div>';

		printf(
			'<button type="button" class="button wps-pick-image" data-target="%s">Select image</button>',
			esc_attr( $id_attr )
		);

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}
}
