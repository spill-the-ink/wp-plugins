<?php
/**
 * Bricks "Favorite Toggle" element.
 *
 * The toggle is rendered with the core Bricks button markup and theme style
 * classes (`bricks-button` plus `bricks-background-*` / `bricks-color-*`), so it
 * inherits the site's Button styles instead of shipping its own.
 *
 * @package WpFavorites
 */

namespace WpFavorites\Bricks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Element_Favorite_Toggle extends \Bricks\Element {
	public $category     = 'general';
	public $name         = 'wp-favorites-toggle';
	public $icon         = 'ti-heart';
	public $css_selector = '.wp-favorites-toggle';
	public $nestable     = false;

	public function get_label() {
		return esc_html__( 'Favorite Toggle', 'wp-favorites' );
	}

	public function get_keywords() {
		return array( 'favorite', 'favourite', 'bookmark', 'save', 'heart', 'wp-favorites' );
	}

	public function set_controls() {
		$this->controls['postSource'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Post source', 'wp-favorites' ),
			'type'    => 'select',
			'options' => array(
				'current' => esc_html__( 'Current post', 'wp-favorites' ),
				'custom'  => esc_html__( 'Specific post', 'wp-favorites' ),
			),
			'default' => 'current',
		);

		$this->controls['postId'] = array(
			'tab'         => 'content',
			'label'       => esc_html__( 'Post ID', 'wp-favorites' ),
			'type'        => 'text',
			'placeholder' => '{post_id}',
			'description' => esc_html__( 'Supports Bricks dynamic data, e.g. {post_id} inside a query loop.', 'wp-favorites' ),
			'required'    => array( 'postSource', '=', 'custom' ),
		);

		$this->controls['addText'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Add text', 'wp-favorites' ),
			'type'    => 'text',
			'default' => esc_html__( 'Add to favorites', 'wp-favorites' ),
		);

		$this->controls['removeText'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Remove text', 'wp-favorites' ),
			'type'    => 'text',
			'default' => esc_html__( 'Remove from favorites', 'wp-favorites' ),
		);

		$this->controls['showIcon'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Show icon', 'wp-favorites' ),
			'type'    => 'checkbox',
			'default' => true,
		);

		$this->controls['buttonStyle'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Style', 'wp-favorites' ),
			'type'    => 'select',
			'options' => self::button_style_options(),
			'default' => 'primary',
		);

		$this->controls['outline'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Outline', 'wp-favorites' ),
			'type'    => 'checkbox',
			'default' => false,
		);
	}

	public function render() {
		$plugin = \WpFavorites\Plugin::instance();

		if ( ! $plugin ) {
			return;
		}

		$plugin->assets()->enqueue_toggle_assets();

		$settings = $this->settings;
		$post_id  = $this->resolve_post_id( $settings );

		if ( ! $post_id || ! wp_favorites_is_favoritable( $post_id ) ) {
			return;
		}

		$is_favorite = wp_favorites_has( $post_id );

		$add_text    = (string) ( $settings['addText'] ?? esc_html__( 'Add to favorites', 'wp-favorites' ) );
		$remove_text = (string) ( $settings['removeText'] ?? esc_html__( 'Remove from favorites', 'wp-favorites' ) );
		$show_icon   = ! isset( $settings['showIcon'] ) || ! empty( $settings['showIcon'] );

		$style   = (string) ( $settings['buttonStyle'] ?? 'primary' );
		$outline = ! empty( $settings['outline'] );

		$classes   = array( 'wp-favorites-toggle', 'bricks-button' );
		$classes[] = $outline
			? 'bricks-color-' . sanitize_html_class( $style )
			: 'bricks-background-' . sanitize_html_class( $style );

		if ( $outline ) {
			$classes[] = 'outline';
		}

		$this->set_attribute( '_root', 'class', $classes );
		$this->set_attribute( '_root', 'type', 'button' );
		$this->set_attribute( '_root', 'data-wp-favorites-toggle', '1' );
		$this->set_attribute( '_root', 'data-post-id', (string) $post_id );
		$this->set_attribute( '_root', 'data-is-favorite', $is_favorite ? '1' : '0' );
		$this->set_attribute( '_root', 'data-label-add', $add_text );
		$this->set_attribute( '_root', 'data-label-remove', $remove_text );
		$this->set_attribute( '_root', 'aria-pressed', $is_favorite ? 'true' : 'false' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bricks render_attributes() returns pre-built, escaped HTML attributes.
		echo '<button ' . $this->render_attributes( '_root' ) . '>';

		if ( $show_icon ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG markup with an escaped attribute.
			echo '<span class="wp-favorites-toggle__icon" aria-hidden="true">' . self::icon_svg( $is_favorite ) . '</span>';
		}

		echo '<span class="wp-favorites-toggle__label">' . esc_html( $is_favorite ? $remove_text : $add_text ) . '</span>';

		echo '</button>';
	}

	/**
	 * Resolve the target post ID, honoring Bricks dynamic data for the custom ID.
	 *
	 * @param array<string, mixed> $settings Element settings.
	 */
	private function resolve_post_id( array $settings ): int {
		if ( 'custom' !== ( $settings['postSource'] ?? 'current' ) ) {
			return (int) get_the_ID();
		}

		$raw = isset( $settings['postId'] ) ? (string) $settings['postId'] : '';

		if ( '' !== $raw && method_exists( $this, 'render_dynamic_data' ) ) {
			$raw = (string) $this->render_dynamic_data( $raw );
		}

		return absint( trim( $raw ) );
	}

	/**
	 * @return array<string, string>
	 */
	private static function button_style_options(): array {
		$styles = Elements::$button_styles;

		if ( empty( $styles ) || ! is_array( $styles ) ) {
			$styles = array(
				'primary'   => esc_html__( 'Primary', 'wp-favorites' ),
				'secondary' => esc_html__( 'Secondary', 'wp-favorites' ),
				'success'   => esc_html__( 'Success', 'wp-favorites' ),
				'warning'   => esc_html__( 'Warning', 'wp-favorites' ),
				'danger'    => esc_html__( 'Danger', 'wp-favorites' ),
				'info'      => esc_html__( 'Info', 'wp-favorites' ),
				'light'     => esc_html__( 'Light', 'wp-favorites' ),
				'dark'      => esc_html__( 'Dark', 'wp-favorites' ),
				'muted'     => esc_html__( 'Muted', 'wp-favorites' ),
			);
		}

		return $styles;
	}

	private static function icon_svg( bool $filled ): string {
		return sprintf(
			'<svg class="wp-favorites-toggle__svg" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false" fill="%1$s" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'
				. '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>'
				. '</svg>',
			$filled ? 'currentColor' : 'none'
		);
	}
}
