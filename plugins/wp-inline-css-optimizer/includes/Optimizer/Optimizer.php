<?php
/**
 * Primary interceptor: minifies inline CSS registered against WP_Styles
 * handles before the styles are printed.
 *
 * Runs on the `wp_print_styles` action at priority 20, which fires after all
 * enqueues/registrations (default priority 10, and core's own priority 1) and
 * before `WP_Styles::do_items()` prints anything.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Optimizer;

use WpInlineCssOptimizer\Debug\Reporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Optimizer {

	private Minifier $minifier;
	private Cache $cache;
	private Reporter $reporter;

	/**
	 * Handles already processed this request (generic pass + Winden fast-path).
	 *
	 * @var array<string, bool>
	 */
	private array $processed = array();

	public function __construct( Minifier $minifier, Cache $cache, Reporter $reporter ) {
		$this->minifier = $minifier;
		$this->cache    = $cache;
		$this->reporter = $reporter;
	}

	public function setup(): void {
		add_action( 'wp_print_styles', array( $this, 'optimize_inline_styles' ), 20 );
	}

	/**
	 * Whether optimization is enabled (option, overridable by filter).
	 */
	public function is_enabled(): bool {
		$default = (bool) \wp_inline_css_optimizer_get_option( 'enabled', 1 );

		return (bool) apply_filters( 'wp_inline_css_optimizer_enabled', $default );
	}

	/**
	 * Iterate every registered handle and minify its inline CSS data.
	 */
	public function optimize_inline_styles(): void {
		$this->reporter->log( 'wp_print_styles@20: fired' );

		if ( ! \wp_inline_css_optimizer_is_frontend_request() ) {
			$this->reporter->log( 'wp_print_styles@20: skipped (not a front-end document request)' );

			return;
		}

		if ( ! $this->is_enabled() ) {
			$this->reporter->mark_run( false );
			$this->reporter->log( 'wp_print_styles@20: skipped (optimization disabled)' );

			return;
		}

		$wp_styles = wp_styles();

		if ( ! $wp_styles || empty( $wp_styles->registered ) ) {
			$this->reporter->log( 'wp_print_styles@20: no registered styles' );

			return;
		}

		$handles = array_keys( $wp_styles->registered );

		$this->reporter->mark_run( true );
		$this->reporter->set_scanned( count( $handles ) );
		$this->reporter->log( sprintf( 'wp_print_styles@20: scanning %d handle(s)', count( $handles ) ) );

		foreach ( $handles as $handle ) {
			$this->process_handle( (string) $handle );
		}
	}

	/**
	 * Minify one handle's inline CSS. Safe to call repeatedly.
	 *
	 * @param string $handle Stylesheet handle.
	 * @return bool Whether anything was changed.
	 */
	public function process_handle( string $handle ): bool {
		if ( isset( $this->processed[ $handle ] ) ) {
			return false;
		}

		$this->processed[ $handle ] = true;

		if ( ! $this->should_process( $handle ) ) {
			return false;
		}

		$wp_styles = wp_styles();

		if ( ! $wp_styles || ! isset( $wp_styles->registered[ $handle ] ) ) {
			return false;
		}

		$min_bytes = (int) \wp_inline_css_optimizer_get_option( 'min_bytes', 200 );
		$changed   = false;
		$bytes_in  = 0;
		$bytes_out = 0;

		foreach ( $this->get_positions() as $position ) {
			$raw = $wp_styles->get_data( $handle, $position );
			$css = $this->normalize( $raw );

			if ( '' === $css ) {
				continue;
			}

			if ( $min_bytes > 0 && strlen( $css ) < $min_bytes ) {
				$this->reporter->log(
					sprintf( '%s: skipped (%d bytes < min_bytes %d)', $handle, strlen( $css ), $min_bytes )
				);

				continue;
			}

			$minified = $this->cache->get( $css );

			if ( null === $minified ) {
				$minified = $this->minifier->minify( $css );
				$this->cache->set( $css, $minified );
			}

			$minified = (string) apply_filters( 'wp_inline_css_optimizer_minified_css', $minified, $css, $handle );

			if ( '' === $minified || $minified === $css ) {
				continue;
			}

			$wp_styles->add_data( $handle, $position, self::format_inline_value( $raw, $minified ) );
			$changed   = true;
			$bytes_in  += strlen( $css );
			$bytes_out += strlen( $minified );

			do_action( 'wp_inline_css_optimizer_minified', $handle, strlen( $css ), strlen( $minified ) );
		}

		if ( $changed ) {
			$this->reporter->record_handle( $handle, $bytes_in, $bytes_out );
		}

		return $changed;
	}

	/**
	 * Preserve the storage shape WordPress expects for inline style data.
	 *
	 * WordPress stores inline `after` CSS as an array (WP_Styles::print_inline_style()
	 * bails unless the value is an array). Passing a bare string to add_data()
	 * therefore silently drops the stylesheet, so we must keep the array shape.
	 *
	 * @param mixed  $original  Original get_data() value (string or array).
	 * @param string $minified  Minified CSS.
	 * @return mixed Value safe to pass to WP_Styles::add_data().
	 */
	public static function format_inline_value( $original, string $minified ) {
		return is_array( $original ) ? array( $minified ) : $minified;
	}

	/**
	 * Whether a handle should be minified.
	 */
	public function should_process( string $handle ): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$included = (array) apply_filters(
			'wp_inline_css_optimizer_included_handles',
			\wp_inline_css_optimizer_parse_handle_list( \wp_inline_css_optimizer_get_option( 'included_handles', '' ) )
		);

		if ( $included && ! in_array( $handle, $included, true ) ) {
			return false;
		}

		$excluded = (array) apply_filters(
			'wp_inline_css_optimizer_excluded_handles',
			\wp_inline_css_optimizer_parse_handle_list( \wp_inline_css_optimizer_get_option( 'excluded_handles', '' ) )
		);

		if ( in_array( $handle, $excluded, true ) ) {
			return false;
		}

		return (bool) apply_filters( 'wp_inline_css_optimizer_should_minify_handle', true, $handle, '' );
	}

	/**
	 * WP_Styles data positions to process (default: the inline `after` data
	 * used by wp_add_inline_style()).
	 *
	 * @return string[]
	 */
	private function get_positions(): array {
		$positions = (array) apply_filters( 'wp_inline_css_optimizer_positions', array( 'after' ) );

		return array_values( array_filter( array_map( 'strval', $positions ) ) );
	}

	/**
	 * Coerce WP_Styles inline data (string or array of strings) to a single
	 * CSS string ready for minification.
	 */
	private function normalize( $data ): string {
		if ( is_string( $data ) ) {
			return '' === trim( $data ) ? '' : $data;
		}

		if ( is_array( $data ) ) {
			$joined = implode( "\n", array_map( 'strval', $data ) );

			return '' === trim( $joined ) ? '' : $joined;
		}

		return '';
	}
}
