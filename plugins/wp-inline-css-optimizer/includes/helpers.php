<?php
/**
 * Shared helpers and option defaults for WordPress Inline CSS Optimizer.
 *
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default plugin options.
 *
 * @return array
 */
function wp_inline_css_optimizer_default_options(): array {
	return array(
		'enabled'                => 1,
		'included_handles'       => '',
		'excluded_handles'       => '',
		'winden_fast_path'       => 1,
		'winden_file_minify'     => 0,
		'output_buffer_fallback' => 0,
		'aggressive'             => 1,
		'min_bytes'              => 200,
		'cache_ttl'              => WEEK_IN_SECONDS,
		'debug'                  => 0,
		'debug_log'              => 0,
		'debug_suggestions'      => 1,
	);
}

/**
 * Get all options merged with defaults (cached per request).
 *
 * @return array
 */
function wp_inline_css_optimizer_get_options(): array {
	static $options = null;

	if ( null === $options ) {
		$new     = get_option( WP_INLINE_CSS_OPTIMIZER_OPTION, array() );
		$options = wp_parse_args( is_array( $new ) ? $new : array(), wp_inline_css_optimizer_default_options() );
	}

	return $options;
}

/**
 * Read a single option value.
 *
 * @param string $key     Option key.
 * @param mixed  $default Fallback when the key is absent.
 * @return mixed
 */
function wp_inline_css_optimizer_get_option( string $key, $default = null ) {
	$options = wp_inline_css_optimizer_get_options();

	return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

/**
 * Normalise a handle list option (newline/comma string or array) to an array
 * of unique, trimmed handles.
 *
 * @param mixed $value Raw option value.
 * @return string[]
 */
function wp_inline_css_optimizer_parse_handle_list( $value ): array {
	if ( is_array( $value ) ) {
		$parts = $value;
	} else {
		$parts = preg_split( '/[\r\n,]+/', (string) $value );
	}

	if ( ! is_array( $parts ) ) {
		return array();
	}

	$handles = array();

	foreach ( $parts as $part ) {
		$part = trim( (string) $part );

		if ( '' !== $part ) {
			$handles[] = $part;
		}
	}

	return array_values( array_unique( $handles ) );
}

/**
 * Whether the current request is a cacheable front-end document request.
 */
function wp_inline_css_optimizer_is_frontend_request(): bool {
	if ( is_admin() ) {
		return false;
	}

	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		return false;
	}

	if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	if ( function_exists( 'is_feed' ) && is_feed() ) {
		return false;
	}

	return true;
}

/**
 * Delete every cached compiled-CSS transient owned by this plugin.
 */
function wp_inline_css_optimizer_flush_css_cache(): void {
	global $wpdb;

	$like_transient = $wpdb->esc_like( '_transient_wp_inline_css_optimizer_css_' ) . '%';
	$like_timeout   = $wpdb->esc_like( '_transient_timeout_wp_inline_css_optimizer_css_' ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$like_transient,
			$like_timeout
		)
	);
}
