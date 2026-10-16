<?php
/**
 * Winden integration.
 *
 * Winden exposes no filter for its compiled CSS, so this integration is a
 * fully guarded fast-path: it re-uses the generic Optimizer for the
 * `winden-compiled-css` handle and optionally minifies the compiled
 * `uploads/winden/output.css` file when Winden (re)generates it.
 *
 * Everything here is inert when Winden is absent.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Integrations;

use WpInlineCssOptimizer\Debug\Reporter;
use WpInlineCssOptimizer\Optimizer\Minifier;
use WpInlineCssOptimizer\Optimizer\Optimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Winden {

	private Optimizer $optimizer;
	private Minifier $minifier;
	private Reporter $reporter;

	public function __construct( Optimizer $optimizer, Minifier $minifier, Reporter $reporter ) {
		$this->optimizer = $optimizer;
		$this->minifier  = $minifier;
		$this->reporter  = $reporter;
	}

	public function setup(): void {
		add_action( 'wp_print_styles', array( $this, 'optimize_winden_inline' ), 21 );

		// Fires after Winden writes uploads/winden/output.css.
		add_action( 'winden_after_css_saved', array( $this, 'minify_saved_output_file' ), 10, 2 );
	}

	/**
	 * Whether the Winden integration is enabled.
	 */
	public function is_enabled(): bool {
		$default = (bool) \wp_inline_css_optimizer_get_option( 'winden_fast_path', 1 );

		return (bool) apply_filters( 'wp_inline_css_optimizer_winden_enabled', $default );
	}

	/**
	 * The stylesheet handle Winden uses for its inline compiled CSS.
	 */
	public function get_handle(): string {
		return (string) apply_filters( 'wp_inline_css_optimizer_winden_handle', 'winden-compiled-css' );
	}

	/**
	 * Guarantee the Winden handle is processed even if the generic pass already
	 * ran (the Optimizer dedupes, so this is always safe).
	 */
	public function optimize_winden_inline(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$handle = $this->get_handle();

		if ( ! wp_style_is( $handle, 'registered' ) ) {
			$this->reporter->log( sprintf( 'wp_print_styles@21: Winden handle "%s" not registered', $handle ) );

			return;
		}

		$this->reporter->record_winden_detected();
		$this->reporter->log( sprintf( 'wp_print_styles@21: Winden handle "%s" detected', $handle ) );

		$this->optimizer->process_handle( $handle );
	}

	/**
	 * Minify the compiled output.css file written by Winden.
	 *
	 * @param string $path   Written file path (provided by Winden).
	 * @param array  $purged Purged cache identifiers (unused).
	 */
	public function minify_saved_output_file( $path = '', $purged = array() ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! \wp_inline_css_optimizer_get_option( 'winden_file_minify', 0 ) ) {
			return;
		}

		$path = is_string( $path ) && '' !== $path ? $path : $this->default_output_path();
		$path = (string) apply_filters( 'wp_inline_css_optimizer_winden_output_path', $path );

		if ( '' === $path || ! is_readable( $path ) || ! is_writable( $path ) ) {
			do_action( 'wp_inline_css_optimizer_winden_file_error', $path, 'unreadable-or-unwritable' );

			return;
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $contents || '' === $contents ) {
			return;
		}

		$minified = $this->minifier->minify( $contents );

		if ( '' === $minified || $minified === $contents ) {
			return;
		}

		$temp = $path . '.wico-tmp';

		if ( false === file_put_contents( $temp, $minified ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			do_action( 'wp_inline_css_optimizer_winden_file_error', $path, 'write-failed' );

			return;
		}

		if ( ! rename( $temp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			do_action( 'wp_inline_css_optimizer_winden_file_error', $path, 'rename-failed' );

			return;
		}

		do_action( 'wp_inline_css_optimizer_winden_file_minified', $path, strlen( $contents ), strlen( $minified ) );

		$this->reporter->record_file( strlen( $contents ), strlen( $minified ) );
		$this->reporter->log(
			sprintf( 'winden_after_css_saved: minified %s (%d -> %d bytes)', basename( $path ), strlen( $contents ), strlen( $minified ) )
		);
	}

	private function default_output_path(): string {
		$uploads = wp_upload_dir();

		return trailingslashit( (string) $uploads['basedir'] ) . 'winden/output.css';
	}
}
