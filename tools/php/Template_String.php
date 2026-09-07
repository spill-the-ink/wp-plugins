<?php
/**
 * Shared template-string renderer for `{{ token }}` placeholders.
 *
 * Resolves dotted lookup keys (e.g. `{{ post.title }}` or `{{ site.description }}`)
 * against a data array, leaves the `{{ sep }}` placeholder for a final
 * normalization pass that inserts a separator and collapses repeats.
 *
 * @package WpPluginShared
 * @since   0.3.0
 */

namespace WpPluginShared;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Template_String {

	/**
	 * Placeholder that normalize() replaces with the actual separator.
	 */
	const SEP_TOKEN = '{{ sep }}';

	/**
	 * Render a template, replacing every `{{ a.b.c }}` token from $data.
	 *
	 * The `{{ sep }}` placeholder is intentionally left as-is so that normalize()
	 * can collapse adjacent segments and repeat separators.
	 */
	public static function render( string $template, array $data = [] ): string {
		$template = (string) preg_replace_callback(
			'/{{[ \t]*([a-z0-9_.]+)[ \t]*}}/i',
			static function ( $matches ) use ( $data ) {
				$key = strtolower( $matches[1] );

				if ( 'sep' === $key ) {
					return self::SEP_TOKEN;
				}

				$value = self::get( $data, $matches[1] );

				return ( null === $value || false === $value ) ? '' : (string) $value;
			},
			$template
		);

		return trim( (string) preg_replace( '/\s+/u', ' ', $template ) );
	}

	/**
	 * Resolve a dotted path inside a nested array.
	 *
	 * @param array  $data Nested data array.
	 * @param string $path Dot-separated key path.
	 * @return mixed|null Null when the path does not resolve.
	 */
	public static function get( array $data, string $path ) {
		$target = $data;

		foreach ( explode( '.', $path ) as $part ) {
			if ( is_array( $target ) && array_key_exists( $part, $target ) ) {
				$target = $target[ $part ];
			} else {
				return null;
			}
		}

		return $target;
	}

	/**
	 * Replace `{{ sep }}` placeholders with a real separator and collapse
	 * repeated/dangling separators so the string reads cleanly.
	 *
	 * @param string $template Rendered template string.
	 * @param string $sep      Separator; defaults to '|'.
	 */
	public static function normalize( string $template, string $sep = '|' ): string {
		$sep = trim( $sep );
		if ( '' === $sep ) {
			$sep = '|';
		}

		if ( false !== strpos( $template, self::SEP_TOKEN ) ) {
			$template = str_replace( self::SEP_TOKEN, $sep, $template );
		}

		$template = (string) preg_replace( '/\s+/u', ' ', $template );

		// Collapse repeats: "A | B | | C" → "A | B | C".
		$double = $sep . ' ' . $sep;
		while ( false !== strpos( $template, $double ) ) {
			$template = str_replace( $double, $sep, $template );
		}

		// Drop a dangling separator at either end.
		$template = preg_replace( '/\s*' . preg_quote( $sep, '/' ) . '\s*$/u', '', $template );
		$template = preg_replace( '/^\s*' . preg_quote( $sep, '/' ) . '\s*/u', '', $template );

		return trim( $template );
	}

	/**
	 * Render then normalize in one call.
	 */
	public static function render_all( string $template, array $data = [], string $sep = '|' ): string {
		return self::normalize( self::render( $template, $data ), $sep );
	}
}