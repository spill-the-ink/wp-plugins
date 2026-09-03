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

class Settings_Store {

	private string $option_name;
	private array  $defaults;
	private ?array $cached = null;

	public function __construct( string $option_name, array $defaults ) {
		$this->option_name = $option_name;
		$this->defaults    = $defaults;
	}

	public function get( string $key = '', $default = null ) {
		$options = $this->all();

		if ( '' === $key ) {
			return $options;
		}

		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}

		return $default;
	}

	public function all(): array {
		if ( null === $this->cached ) {
			$this->cached = wp_parse_args(
				get_option( $this->option_name, [] ),
				$this->defaults
			);
		}

		return $this->cached;
	}

	public function update( array $data ): void {
		$this->cached = null;
		update_option( $this->option_name, $data );
	}

	public function defaults(): array {
		return $this->defaults;
	}

	public function reset(): void {
		$this->cached = null;
	}
}
