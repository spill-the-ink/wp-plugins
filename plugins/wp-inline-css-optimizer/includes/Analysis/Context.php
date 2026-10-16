<?php
/**
 * Immutable facts collected for the current request, consumed by Analysis\Checks.
 *
 * This is deliberately a plain data object so checks are trivial to unit test:
 * build a Context from fixtures and assert on the findings.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Context {

	/**
	 * Stylesheet summaries.
	 *
	 * @var array<int, array{handle:string, external:bool, host:string, inline_bytes:int, processed:bool}>
	 */
	public array $styles;

	/**
	 * Script summaries.
	 *
	 * @var array<int, array{handle:string, external:bool, host:string, in_footer:bool, strategy:string}>
	 */
	public array $scripts;

	/**
	 * Captured resource hints keyed by relation type.
	 *
	 * @var array<string, array<int, mixed>>
	 */
	public array $resource_hints;

	public int $font_faces;

	/**
	 * Local woff2 URLs referenced by @font-face rules.
	 *
	 * @var string[]
	 */
	public array $font_files;

	public bool $font_preload_present;

	/**
	 * Plugin settings snapshot.
	 *
	 * @var array<string, mixed>
	 */
	public array $settings;

	/**
	 * Minification totals: bytes_in, bytes_out, handles.
	 *
	 * @var array{bytes_in:int, bytes_out:int, handles:int}
	 */
	public array $totals;

	/**
	 * Large inline CSS blocks that bypassed the optimizer.
	 *
	 * @var array<int, array{handle:string, bytes:int}>
	 */
	public array $bypassed;

	public string $local_host;

	public function __construct(
		array $styles = array(),
		array $scripts = array(),
		array $resource_hints = array(),
		int $font_faces = 0,
		array $font_files = array(),
		array $settings = array(),
		array $totals = array(),
		array $bypassed = array(),
		string $local_host = ''
	) {
		$this->styles          = array_values( $styles );
		$this->scripts         = array_values( $scripts );
		$this->resource_hints  = $resource_hints;
		$this->font_faces      = max( 0, $font_faces );
		$this->font_files      = array_values( array_map( 'strval', $font_files ) );
		$this->settings        = $settings;
		$this->totals          = array(
			'bytes_in'  => (int) ( $totals['bytes_in'] ?? 0 ),
			'bytes_out' => (int) ( $totals['bytes_out'] ?? 0 ),
			'handles'   => (int) ( $totals['handles'] ?? 0 ),
		);
		$this->bypassed        = array_values( $bypassed );
		$this->local_host      = $local_host;

		$this->font_preload_present = $this->detect_font_preload( $resource_hints );
	}

	/**
	 * Resource hints registered for a relation type.
	 *
	 * @return array<int, mixed>
	 */
	public function hints_for( string $relation ): array {
		return isset( $this->resource_hints[ $relation ] ) && is_array( $this->resource_hints[ $relation ] )
			? $this->resource_hints[ $relation ]
			: array();
	}

	/**
	 * Unique cross-origin hosts referenced by styles or scripts.
	 *
	 * @return string[]
	 */
	public function external_hosts(): array {
		$hosts = array();

		foreach ( array_merge( $this->styles, $this->scripts ) as $item ) {
			$host = (string) ( $item['host'] ?? '' );

			if ( '' === $host || $host === $this->local_host ) {
				continue;
			}

			$hosts[ $host ] = true;
		}

		return array_keys( $hosts );
	}

	/**
	 * Whether any hint URL already covers a host.
	 *
	 * @param string[] $hint_urls Hint values (URLs or host strings).
	 */
	public function host_is_hinted( string $host, array $hint_urls ): bool {
		foreach ( $hint_urls as $url ) {
			if ( false !== strpos( (string) $url, $host ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Number of external (registered with a src) stylesheets.
	 */
	public function external_style_count(): int {
		$count = 0;

		foreach ( $this->styles as $style ) {
			if ( ! empty( $style['external'] ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Whether a captured resource hint already preloads a font.
	 *
	 * @param array<string, array<int, mixed>> $hints Resource hints by relation.
	 */
	private function detect_font_preload( array $hints ): bool {
		$preloads = isset( $hints['preload'] ) && is_array( $hints['preload'] ) ? $hints['preload'] : array();

		foreach ( $preloads as $entry ) {
			if ( is_array( $entry ) ) {
				if ( isset( $entry['as'] ) && 'font' === strtolower( (string) $entry['as'] ) ) {
					return true;
				}

				continue;
			}

			if ( is_string( $entry ) && preg_match( '/\bas=([\'"]?)font\1/i', $entry ) ) {
				return true;
			}
		}

		return false;
	}
}
