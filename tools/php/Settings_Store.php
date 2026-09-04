<?php
/**
 * Shared settings store — option retrieval with defaults merging and caching.
 *
 * @package WpPluginShared
 * @since   0.1.0
 */

namespace WpPluginShared;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Store {

	private string $option_name;
	private array  $defaults;
	private ?array $cached = null;

	public function __construct( string $option_name, array $defaults ) {
		$this->option_name = $option_name;
		$this->defaults    = $defaults;
	}

	/**
	 * Get a single option value, or the full options array when $key is empty.
	 */
	public function get( string $key = '', $default = null ) {
		$options = $this->all();

		if ( '' === $key ) {
			return $options;
		}

		return $options[ $key ] ?? $default;
	}

	/**
	 * Get all options merged with defaults. Cached per request.
	 */
	public function all(): array {
		if ( null === $this->cached ) {
			$raw = get_option( $this->option_name, [] );
			$this->cached = wp_parse_args( is_array( $raw ) ? $raw : [], $this->defaults );
		}

		return $this->cached;
	}

	/**
	 * Save all options at once (full replace).
	 */
	public function update( array $data ): void {
		update_option( $this->option_name, $data );
		$this->cached = null;
	}

	/**
	 * Return the defaults array.
	 */
	public function defaults(): array {
		return $this->defaults;
	}

	/**
	 * Force a cache refresh on next get().
	 */
	public function reset(): void {
		$this->cached = null;
	}
}
