<?php
/**
 * Opt-in output-buffer fallback for raw <style> tags in the head.
 *
 * Styles printed directly with echo (bypassing the enqueue API) cannot be
 * reached via WP_Styles. When explicitly enabled, this buffers the `wp_head`
 * output and rewrites every <style> element with WP_HTML_Tag_Processor.
 *
 * Off by default. Requires WordPress 6.5+ (silently a no-op below that).
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Integrations;

use WpInlineCssOptimizer\Debug\Reporter;
use WpInlineCssOptimizer\Optimizer\Cache;
use WpInlineCssOptimizer\Optimizer\Minifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Output_Buffer {

	private Minifier $minifier;
	private Cache $cache;
	private Reporter $reporter;

	private int $level  = 0;
	private bool $active = false;

	public function __construct( Minifier $minifier, Cache $cache, Reporter $reporter ) {
		$this->minifier = $minifier;
		$this->cache    = $cache;
		$this->reporter = $reporter;
	}

	public function setup(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return;
		}

		add_action( 'wp_head', array( $this, 'start' ), 0 );
		add_action( 'wp_head', array( $this, 'finish' ), PHP_INT_MAX );
	}

	public function is_enabled(): bool {
		$default = (bool) \wp_inline_css_optimizer_get_option( 'output_buffer_fallback', 0 );

		return (bool) apply_filters( 'wp_inline_css_optimizer_output_buffer_enabled', $default );
	}

	/**
	 * Open the buffer at the very start of wp_head.
	 */
	public function start(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->level  = ob_get_level();
		$this->active = true;

		ob_start();
	}

	/**
	 * Close the buffer very late in wp_head, only if ours is still topmost.
	 */
	public function finish(): void {
		if ( ! $this->active ) {
			return;
		}

		$this->active = false;

		// Bail without touching the buffer if another plugin opened one on top.
		if ( ob_get_level() !== $this->level + 1 ) {
			return;
		}

		$html = ob_get_clean();

		if ( ! is_string( $html ) || '' === $html ) {
			return;
		}

		echo $this->process_html( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function process_html( string $html ): string {
		$processor = new \WP_HTML_Tag_Processor( $html );
		$changed   = false;
		$tags      = 0;
		$bytes_in  = 0;
		$bytes_out = 0;

		while ( $processor->next_tag( 'STYLE' ) ) {
			$css = $processor->get_modifiable_text();

			if ( ! is_string( $css ) || '' === trim( $css ) ) {
				continue;
			}

			$minified = $this->cache->get( $css );

			if ( null === $minified ) {
				$minified = $this->minifier->minify( $css );
				$this->cache->set( $css, $minified );
			}

			if ( '' === $minified || $minified === $css ) {
				continue;
			}

			$processor->set_modifiable_text( $minified );
			$changed = true;
			$tags++;
			$bytes_in  += strlen( $css );
			$bytes_out += strlen( $minified );
		}

		$this->reporter->record_buffer( $tags, $bytes_in, $bytes_out );

		if ( ! $changed ) {
			return $html;
		}

		return (string) apply_filters( 'wp_inline_css_optimizer_minified_style_tag', $processor->get_updated_html(), $html );
	}
}
