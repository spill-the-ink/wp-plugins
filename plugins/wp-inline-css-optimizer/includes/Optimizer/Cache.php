<?php
/**
 * Transient-backed cache for minified CSS, memoised per request.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Optimizer;

use WpInlineCssOptimizer\Debug\Reporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cache {

	/**
	 * Per-request memo keyed by cache key.
	 *
	 * @var array<string, string|null>
	 */
	private array $memo = array();

	private ?Reporter $reporter;

	public function __construct( ?Reporter $reporter = null ) {
		$this->reporter = $reporter;
	}

	/**
	 * Cache key for a given input. Versioned so plugin upgrades invalidate.
	 */
	public function key( string $css ): string {
		$key = 'wp_inline_css_optimizer_css_' . md5( WP_INLINE_CSS_OPTIMIZER_VERSION . '|' . $css );

		return (string) apply_filters( 'wp_inline_css_optimizer_css_transient_key', $key, $css );
	}

	/**
	 * Cache lifetime in seconds.
	 */
	public function ttl(): int {
		$ttl = (int) \wp_inline_css_optimizer_get_option( 'cache_ttl', WEEK_IN_SECONDS );

		if ( $ttl <= 0 ) {
			$ttl = WEEK_IN_SECONDS;
		}

		return (int) apply_filters( 'wp_inline_css_optimizer_cache_ttl', $ttl );
	}

	/**
	 * Return cached minified CSS, or null on miss.
	 */
	public function get( string $css ): ?string {
		$key = $this->key( $css );

		if ( array_key_exists( $key, $this->memo ) ) {
			return $this->memo[ $key ];
		}

		$cached           = get_transient( $key );
		$this->memo[ $key ] = is_string( $cached ) ? $cached : null;

		if ( null !== $this->reporter ) {
			$this->reporter->record_cache( null !== $this->memo[ $key ] );
		}

		return $this->memo[ $key ];
	}

	/**
	 * Store minified CSS for a given input.
	 */
	public function set( string $css, string $minified ): void {
		$key                = $this->key( $css );
		$this->memo[ $key ] = $minified;

		set_transient( $key, $minified, $this->ttl() );
	}

	/**
	 * Delete every cached entry owned by this plugin.
	 */
	public function flush_all(): void {
		$this->memo = array();
		\wp_inline_css_optimizer_flush_css_cache();
	}
}
