<?php
/**
 * Developer mode reporter.
 *
 * When the `debug` option is enabled (and the current user is privileged),
 * this collects everything the plugin does during a request and prints a
 * readable report as an HTML comment at the end of the rendered page, so the
 * effect of the plugin (and the benefit of tuning it) is visible in the page
 * source.
 *
 * When the `debug_log` option is enabled it also writes every step to the
 * WordPress debug log (error_log / debug.log), including any fatal error and
 * the final report, which is useful when a failure prevents the page (and
 * therefore the HTML comment) from rendering fully.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Debug;

use WpInlineCssOptimizer\Analysis\Advisor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reporter {

	private ?Advisor $advisor = null;

	private bool $suggestions_computed = false;

	/**
	 * Formatted advisory lines, computed once per request.
	 *
	 * @var string[]
	 */
	private array $suggestions = array();

	private bool $visible_computed = false;
	private bool $visible          = false;
	private bool $enabled_computed = false;
	private bool $enabled          = false;
	private bool $logging_computed = false;
	private bool $logging          = false;
	private bool $rendered         = false;
	private bool $shutdown_done    = false;

	private bool $ran    = false;
	private int $scanned = 0;

	/**
	 * Per-handle results: handle => [ original, minified ].
	 *
	 * @var array<string, array{original:int, minified:int}>
	 */
	private array $handles = array();

	/**
	 * Free-form event log lines.
	 *
	 * @var string[]
	 */
	private array $events = array();

	private int $cache_hits   = 0;
	private int $cache_misses = 0;

	private int $buffer_tags          = 0;
	private int $buffer_bytes_in      = 0;
	private int $buffer_bytes_out     = 0;
	private bool $buffer_enabled_seen = false;

	private int $files_minified    = 0;
	private int $files_bytes_saved = 0;

	private bool $winden_detected = false;

	/**
	 * @param Advisor|null $advisor Advisor used to build the suggestions section.
	 */
	public function __construct( ?Advisor $advisor = null ) {
		$this->advisor = $advisor;
	}

	/**
	 * Lazily-built advisor (kept lazy so it is only constructed when needed).
	 */
	private function advisor(): Advisor {
		if ( null === $this->advisor ) {
			$this->advisor = new Advisor();
		}

		return $this->advisor;
	}

	/**
	 * Whether the advisory suggestions section is active for this request.
	 */
	public function is_suggestions(): bool {
		$default = (bool) \wp_inline_css_optimizer_get_option( 'debug_suggestions', 1 );

		return (bool) apply_filters( 'wp_inline_css_optimizer_debug_suggestions', $default );
	}

	/**
	 * Whether the current visitor may see developer output at all.
	 *
	 * Developer mode must never affect non-admins: nothing is recorded, logged
	 * or printed for a visitor who is not allowed to see the report. Defaults
	 * to logged-in administrators.
	 */
	public function is_visible(): bool {
		if ( $this->visible_computed ) {
			return $this->visible;
		}

		$this->visible_computed = true;

		/**
		 * Whether developer mode may affect the current visitor.
		 * Defaults to logged-in administrators only.
		 */
		$this->visible = (bool) apply_filters(
			'wp_inline_css_optimizer_debug_visible',
			is_user_logged_in() && current_user_can( 'manage_options' )
		);

		return $this->visible;
	}

	/**
	 * Whether the on-page HTML report is active for this request.
	 */
	public function is_enabled(): bool {
		if ( $this->enabled_computed ) {
			return $this->enabled;
		}

		$this->enabled_computed = true;

		if ( ! $this->is_visible() ) {
			$this->enabled = false;

			return $this->enabled;
		}

		$default = (bool) \wp_inline_css_optimizer_get_option( 'debug', 0 );

		$this->enabled = (bool) apply_filters( 'wp_inline_css_optimizer_debug_enabled', $default );

		return $this->enabled;
	}

	/**
	 * Whether reporting to the WordPress debug log is active for this request.
	 */
	public function is_logging(): bool {
		if ( $this->logging_computed ) {
			return $this->logging;
		}

		$this->logging_computed = true;

		if ( ! $this->is_visible() ) {
			$this->logging = false;

			return $this->logging;
		}

		$default = (bool) \wp_inline_css_optimizer_get_option( 'debug_log', 0 );

		$this->logging = (bool) apply_filters( 'wp_inline_css_optimizer_debug_log', $default );

		return $this->logging;
	}

	/**
	 * Whether anything should be recorded at all.
	 */
	public function is_active(): bool {
		return $this->is_enabled() || $this->is_logging();
	}

	/**
	 * Register the footer render hook and the shutdown logger.
	 */
	public function setup(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		if ( $this->is_logging() ) {
			$this->write_log( '=== request ' . $this->current_url() . ' ===' );
			register_shutdown_function( array( $this, 'log_shutdown' ) );
		}

		if ( $this->is_suggestions() ) {
			$this->advisor()->setup();
		}

		if ( $this->is_enabled() ) {
			add_action( 'wp_footer', array( $this, 'render' ), PHP_INT_MAX );
		}
	}

	/**
	 * Record whether the optimizer actually ran on this request.
	 */
	public function mark_run( bool $ran ): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->ran = $ran;
	}

	/**
	 * Record how many handles were scanned.
	 */
	public function set_scanned( int $count ): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->scanned = max( 0, $count );
	}

	/**
	 * Append a timestamped event line.
	 *
	 * @param string $message Event text.
	 */
	public function log( string $message ): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->events[] = gmdate( 'H:i:s' ) . '  ' . $message;

		if ( $this->is_logging() ) {
			$this->write_log( $message );
		}
	}

	/**
	 * Record a minified handle.
	 */
	public function record_handle( string $handle, int $original, int $minified ): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->handles[ $handle ] = array(
			'original' => $original,
			'minified' => $minified,
		);

		if ( 'winden-compiled-css' === $handle ) {
			$this->winden_detected = true;
		}

		if ( $this->is_logging() ) {
			$this->write_log(
				sprintf( 'minified handle "%s": %d -> %d bytes', $handle, $original, $minified )
			);
		}
	}

	/**
	 * Record a cache lookup result.
	 */
	public function record_cache( bool $hit ): void {
		if ( ! $this->is_active() ) {
			return;
		}

		if ( $hit ) {
			$this->cache_hits++;
		} else {
			$this->cache_misses++;
		}
	}

	/**
	 * Record that the Winden handle was detected in the queue.
	 */
	public function record_winden_detected(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->winden_detected = true;
	}

	/**
	 * Record output-buffer statistics.
	 */
	public function record_buffer( int $tags, int $bytes_in, int $bytes_out ): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->buffer_enabled_seen = true;
		$this->buffer_tags        += $tags;
		$this->buffer_bytes_in    += $bytes_in;
		$this->buffer_bytes_out   += $bytes_out;

		if ( $this->is_logging() ) {
			$this->write_log(
				sprintf( 'output buffer: %d tag(s), %d -> %d bytes', $tags, $bytes_in, $bytes_out )
			);
		}
	}

	/**
	 * Record a minified file (Winden compiled output.css).
	 */
	public function record_file( int $bytes_in, int $bytes_out ): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->files_minified++;
		$this->files_bytes_saved += max( 0, $bytes_in - $bytes_out );
	}

	/**
	 * Print the report at the very end of the page.
	 *
	 * Emitted twice: as an HTML comment (visible via View Source) and as a
	 * console.log() call (visible in the browser DevTools console).
	 */
	public function render(): void {
		if ( $this->rendered || ! $this->is_enabled() ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		$this->rendered = true;

		$report = $this->build_report();

		echo "\n<!--\n" . $this->safe( $report ) . "\n-->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML comment, sanitized against terminators.

		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $report ) : json_encode( $report );

		if ( is_string( $json ) ) {
			// Prevent </script> (or any tag) inside the JSON from breaking out.
			$json = str_replace( '<', '\u003C', $json );

			$label = function_exists( 'esc_js' )
				? esc_js( 'WP Inline CSS Optimizer - Developer Mode' )
				: 'WP Inline CSS Optimizer - Developer Mode';

			echo '<script id="wp-inline-css-optimizer-debug">console.log("%c' . $label . '","font-weight:bold;color:#2271b1");console.log(' . $json . ');</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-only developer output.
		}
	}

	/**
	 * Shutdown hook: log any fatal error and the final report.
	 */
	public function log_shutdown(): void {
		if ( ! $this->is_logging() || $this->shutdown_done ) {
			return;
		}

		$this->shutdown_done = true;

		$error = error_get_last();

		if ( is_array( $error ) && in_array(
			(int) $error['type'],
			array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ),
			true
		) ) {
			$this->write_log(
				sprintf(
					'FATAL %s in %s:%d',
					(string) $error['message'],
					(string) $error['file'],
					(int) $error['line']
				)
			);
		}

		$this->write_log( "\n" . $this->build_report() );
	}

	/**
	 * Formatted advisory suggestions, computed once per request.
	 *
	 * @return string[]
	 */
	private function get_suggestions(): array {
		if ( $this->suggestions_computed ) {
			return $this->suggestions;
		}

		$this->suggestions_computed = true;

		if ( ! $this->is_active() || ! $this->is_suggestions() ) {
			return $this->suggestions;
		}

		$totals = array(
			'bytes_in'  => 0,
			'bytes_out' => 0,
			'handles'   => count( $this->handles ),
		);

		foreach ( $this->handles as $stats ) {
			$totals['bytes_in']  += (int) $stats['original'];
			$totals['bytes_out'] += (int) $stats['minified'];
		}

		$findings = $this->advisor()->analyze( array_keys( $this->handles ), $totals );

		$this->suggestions = Advisor::format( $findings );

		if ( $this->is_logging() ) {
			$this->write_log( "\nSUGGESTIONS\n" . ( $this->suggestions ? implode( "\n", $this->suggestions ) : '(none)' ) );
		}

		return $this->suggestions;
	}

	/**
	 * Build the full report text (no HTML comment wrapper).
	 */
	private function build_report(): string {
		$total_in  = 0;
		$total_out = 0;

		$lines   = array();
		$lines[] = str_repeat( '=', 66 );
		$lines[] = 'WP Inline CSS Optimizer - Developer Mode';
		$lines[] = str_repeat( '=', 66 );
		$lines[] = 'Request       : ' . $this->current_url();
		$lines[] = 'Plugin version: ' . WP_INLINE_CSS_OPTIMIZER_VERSION;
		$lines[] = 'Run           : ' . ( $this->ran ? 'yes' : 'no (hook did not run - cached response or disabled)' );
		$lines[] = 'Handles marked: ' . $this->scanned;
		$lines[] = 'Winden handle : ' . ( $this->winden_detected ? 'detected' : 'not detected' );
		$lines[] = '';
		$lines[] = sprintf( '%-34s %12s %12s %14s', 'Handle', 'Bytes in', 'Bytes out', 'Saved' );
		$lines[] = str_repeat( '-', 66 );

		$handles = $this->handles;
		uasort(
			$handles,
			static function ( $a, $b ) {
				return ( $b['original'] - $b['minified'] ) <=> ( $a['original'] - $a['minified'] );
			}
		);

		foreach ( $handles as $handle => $stats ) {
			$in    = (int) $stats['original'];
			$out   = (int) $stats['minified'];
			$saved = max( 0, $in - $out );

			$total_in  += $in;
			$total_out += $out;

			$lines[] = sprintf(
				'%-34s %12s %12s %14s',
				$this->truncate( (string) $handle, 34 ),
				number_format_i18n( $in ),
				number_format_i18n( $out ),
				sprintf( '%s (%.1f%%)', number_format_i18n( $saved ), $this->percent( $in, $out ) )
			);
		}

		if ( empty( $handles ) ) {
			$lines[] = '  (no inline styles were minified)';
		}

		$lines[] = str_repeat( '-', 66 );
		$lines[] = sprintf(
			'%-34s %12s %12s %14s',
			'TOTAL',
			number_format_i18n( $total_in ),
			number_format_i18n( $total_out ),
			sprintf( '%s (%.1f%%)', number_format_i18n( max( 0, $total_in - $total_out ) ), $this->percent( $total_in, $total_out ) )
		);

		$lines[] = '';
		$lines[] = 'Cache               : ' . number_format_i18n( $this->cache_hits ) . ' hit(s), ' . number_format_i18n( $this->cache_misses ) . ' miss(es)';
		$lines[] = 'Head buffer fallback: ' . (
			$this->buffer_enabled_seen
				? sprintf(
					'on - %s tag(s), %s -> %s bytes',
					number_format_i18n( $this->buffer_tags ),
					number_format_i18n( $this->buffer_bytes_in ),
					number_format_i18n( $this->buffer_bytes_out )
				)
				: 'off'
		);
		$lines[] = 'Files rewritten     : ' . number_format_i18n( $this->files_minified ) . ' (' . number_format_i18n( $this->files_bytes_saved ) . ' bytes saved)';

		if ( $this->is_active() && $this->is_suggestions() ) {
			$suggestions = $this->get_suggestions();

			$lines[] = '';
			$lines[] = 'Suggestions:';

			if ( $suggestions ) {
				foreach ( $suggestions as $suggestion ) {
					$lines[] = '  ' . $suggestion;
				}
			} else {
				$lines[] = '  (none)';
			}
		}

		if ( $this->events ) {
			$lines[] = '';
			$lines[] = 'Events:';

			foreach ( $this->events as $event ) {
				$lines[] = '  ' . $event;
			}
		}

		$lines[] = str_repeat( '=', 66 );

		return implode( "\n", $lines );
	}

	/**
	 * Current request URL.
	 */
	private function current_url(): string {
		if ( function_exists( 'home_url' ) && function_exists( 'add_query_arg' ) ) {
			return (string) home_url( add_query_arg( array() ) );
		}

		return '-';
	}

	/**
	 * Write one line to the WordPress debug log.
	 */
	private function write_log( string $message ): void {
		error_log( '[wp-inline-css-optimizer] ' . str_replace( array( "\r\n", "\r" ), "\n", $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Percentage saved, guarding against division by zero.
	 */
	private function percent( int $in, int $out ): float {
		if ( $in <= 0 ) {
			return 0.0;
		}

		return max( 0.0, ( 1 - ( $out / $in ) ) * 100 );
	}

	/**
	 * Truncate a string to a maximum length.
	 */
	private function truncate( string $value, int $length ): string {
		if ( strlen( $value ) <= $length ) {
			return $value;
		}

		return substr( $value, 0, max( 0, $length - 1 ) ) . '~';
	}

	/**
	 * Make a string safe to embed inside an HTML comment.
	 */
	private function safe( string $value ): string {
		return str_replace( array( "\r\n", "\r", '--' ), array( "\n", "\n", '- -' ), $value );
	}
}
