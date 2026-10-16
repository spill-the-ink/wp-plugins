<?php
/**
 * Builds an Analysis\Context from the current WordPress request.
 *
 * Phase 1: derives everything from the enqueue queues, the captured resource
 * hints, and theme.json font-face CSS. No output buffering, so the rendered
 * page is never altered.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Collector {

	/**
	 * Size (bytes) above which unprocessed inline CSS is worth flagging.
	 */
	private const BYPASS_THRESHOLD = 2000;

	/**
	 * Captured resource hints keyed by relation type.
	 *
	 * @var array<string, array<int, mixed>>
	 */
	private array $hints = array();

	/**
	 * Register the resource-hints capture filter.
	 */
	public function setup(): void {
		add_filter( 'wp_resource_hints', array( $this, 'capture_hints' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Capture the final resource hints emitted by core.
	 *
	 * @param array<int, mixed> $hints         Hint values.
	 * @param string            $relation_type Relation type (preconnect, ...).
	 * @return array<int, mixed> Unmodified hints.
	 */
	public function capture_hints( $hints, $relation_type ) {
		$this->hints[ (string) $relation_type ] = is_array( $hints ) ? $hints : array();

		return $hints;
	}

	/**
	 * Collect the request context.
	 *
	 * @param string[]             $processed_handles Handles the optimizer already minified.
	 * @param array<string, mixed> $totals            Minification totals (bytes_in, bytes_out, handles).
	 */
	public function collect( array $processed_handles = array(), array $totals = array() ): Context {
		$local_host = $this->host_of( home_url() );

		$styles          = array();
		$inline_css      = '';
		$processed_lookup = array_fill_keys( array_map( 'strval', $processed_handles ), true );

		if ( function_exists( 'wp_styles' ) ) {
			$wp_styles = wp_styles();

			if ( $wp_styles && ! empty( $wp_styles->registered ) ) {
				foreach ( $wp_styles->registered as $handle => $dependency ) {
					$handle = (string) $handle;
					$src    = isset( $dependency->src ) ? $dependency->src : false;
					$data   = $wp_styles->get_data( $handle, 'after' );
					$inline = $this->inline_string( $data );

					$inline_css .= $inline . "\n";

					$styles[] = array(
						'handle'       => $handle,
						'external'     => is_string( $src ) && '' !== $src,
						'host'         => is_string( $src ) ? $this->host_of( $src ) : '',
						'inline_bytes' => strlen( $inline ),
						'processed'    => isset( $processed_lookup[ $handle ] ),
					);
				}
			}
		}

		$scripts = array();

		if ( function_exists( 'wp_scripts' ) ) {
			$wp_scripts = wp_scripts();

			if ( $wp_scripts && ! empty( $wp_scripts->registered ) ) {
				foreach ( $wp_scripts->registered as $handle => $dependency ) {
					$handle   = (string) $handle;
					$src      = isset( $dependency->src ) ? $dependency->src : false;
					$group    = isset( $dependency->extra['group'] ) ? (int) $dependency->extra['group'] : 0;
					$strategy = '';

					if ( method_exists( $wp_scripts, 'get_data' ) ) {
						$strategy = (string) $wp_scripts->get_data( $handle, 'strategy' );
					}

					$scripts[] = array(
						'handle'    => $handle,
						'external'  => is_string( $src ) && '' !== $src,
						'host'      => is_string( $src ) ? $this->host_of( $src ) : '',
						'in_footer' => 1 === $group,
						'strategy'  => $strategy,
					);
				}
			}
		}

		$font_css   = $this->global_fonts_css() . "\n" . $inline_css;
		$font_files = $this->detect_font_files( $font_css );
		$font_faces = (int) preg_match_all( '/@font-face/i', $font_css );

		return new Context(
			$styles,
			$scripts,
			$this->hints,
			$font_faces,
			$font_files,
			\wp_inline_css_optimizer_get_options(),
			$totals,
			$this->detect_bypassed( $styles ),
			$local_host
		);
	}

	/**
	 * Extract a lowercase host from a URL.
	 */
	private function host_of( string $url ): string {
		$host = function_exists( 'wp_parse_url' )
			? wp_parse_url( $url, PHP_URL_HOST )
			: parse_url( $url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- fallback when wp_parse_url is unavailable (unit tests).

		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Coerce WP_Styles inline data (string|array) to a string.
	 *
	 * @param mixed $data Inline data.
	 */
	private function inline_string( $data ): string {
		if ( is_array( $data ) ) {
			return implode( "\n", array_map( 'strval', $data ) );
		}

		return is_string( $data ) ? $data : '';
	}

	/**
	 * Font-face CSS generated from theme.json / the Font Library.
	 */
	private function global_fonts_css(): string {
		if ( ! function_exists( 'wp_get_global_stylesheet' ) ) {
			return '';
		}

		$css = wp_get_global_stylesheet( array( 'fonts' ) );

		return is_string( $css ) ? $css : '';
	}

	/**
	 * Unique local woff2 URLs referenced by @font-face rules.
	 *
	 * @return string[]
	 */
	private function detect_font_files( string $css ): array {
		if ( '' === trim( $css ) ) {
			return array();
		}

		if ( ! preg_match_all( '/url\(\s*[\'"]?([^\'")]+\.woff2)[\'"]?\s*\)/i', $css, $matches ) ) {
			return array();
		}

		return array_values( array_unique( $matches[1] ) );
	}

	/**
	 * Large inline CSS blocks that were registered but not minified by us.
	 *
	 * @param array<int, array{handle:string, external:bool, host:string, inline_bytes:int, processed:bool}> $styles Styles.
	 * @return array<int, array{handle:string, bytes:int}>
	 */
	private function detect_bypassed( array $styles ): array {
		$bypassed = array();

		foreach ( $styles as $style ) {
			if ( ! empty( $style['external'] ) || ! empty( $style['processed'] ) ) {
				continue;
			}

			if ( $style['inline_bytes'] >= self::BYPASS_THRESHOLD ) {
				$bypassed[] = array(
					'handle' => $style['handle'],
					'bytes'  => $style['inline_bytes'],
				);
			}
		}

		return $bypassed;
	}
}
