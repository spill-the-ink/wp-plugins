<?php
/**
 * Conservative, dependency-free CSS minifier.
 *
 * A single ASCII state machine removes comments (except `/*! ... *​/` banners)
 * and collapses whitespace. It never alters the contents of strings, `url()`
 * values, or `calc()`-style spacing, so the output is safe to inline.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Optimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Minifier {

	/**
	 * Explicit aggressiveness override (used by tests). Null reads options.
	 *
	 * @var bool|null
	 */
	private ?bool $aggressive_override;

	public function __construct( ?bool $aggressive = null ) {
		$this->aggressive_override = $aggressive;
	}

	/**
	 * Whether aggressive whitespace stripping around symbols is enabled.
	 */
	public function is_aggressive(): bool {
		if ( null !== $this->aggressive_override ) {
			return $this->aggressive_override;
		}

		$default = (bool) \wp_inline_css_optimizer_get_option( 'aggressive', 1 );

		return (bool) apply_filters( 'wp_inline_css_optimizer_minifier_aggressive', $default );
	}

	/**
	 * Minify a CSS string. Returns the original string when the result would be
	 * empty, unsafe, or structurally broken.
	 */
	public function minify( string $css ): string {
		if ( '' === trim( $css ) ) {
			return $css;
		}

		$original = $css;
		$out      = $this->compress( $css );

		if ( ! $this->is_safe( $original, $out ) ) {
			return $original;
		}

		return $out;
	}

	/**
	 * Walk the CSS once, dropping comments and normalising whitespace.
	 */
	private function compress( string $css ): string {
		$aggressive      = $this->is_aggressive();
		$length          = strlen( $css );
		$out             = '';
		$i               = 0;
		$pending_space   = false;
		$no_space_before = $aggressive ? array( '{', '}', ';', ',' ) : array();
		$no_space_after  = $aggressive ? array( '{', '}', ';', ',', ':' ) : array();

		while ( $i < $length ) {
			$char = $css[ $i ];

			// Comments: drop all except licence/banner comments (/*! ... */).
			if ( '/' === $char && ( $i + 1 ) < $length && '*' === $css[ $i + 1 ] ) {
				$end = strpos( $css, '*/', $i + 2 );

				if ( false === $end ) {
					break; // Unterminated comment — let the safety guard reject it.
				}

				$comment = substr( $css, $i, ( $end + 2 ) - $i );

				if ( isset( $comment[2] ) && '!' === $comment[2] ) {
					$out           .= rtrim( $comment );
					$pending_space  = false;
				}

				$i = $end + 2;
				continue;
			}

			// Quoted strings are copied verbatim.
			if ( '"' === $char || "'" === $char ) {
				$j             = $this->scan_string( $css, $i, $char );
				$out          .= substr( $css, $i, $j - $i );
				$pending_space = false;
				$i             = $j;
				continue;
			}

			// url() contents are copied verbatim.
			if ( ( 'u' === $char || 'U' === $char ) && $this->is_url_start( $css, $i ) ) {
				$j             = $this->scan_url( $css, $i );
				$out          .= substr( $css, $i, $j - $i );
				$pending_space = false;
				$i             = $j;
				continue;
			}

			// Whitespace: defer a single space until the next real character.
			if ( $this->is_whitespace( $char ) ) {
				$pending_space = true;
				$i++;
				continue;
			}

			if ( $pending_space ) {
				$last = '' !== $out ? $out[ strlen( $out ) - 1 ] : '';

				if ( ! in_array( $char, $no_space_before, true ) && ! in_array( $last, $no_space_after, true ) ) {
					$out .= ' ';
				}

				$pending_space = false;
			}

			$out .= $char;
			$i++;
		}

		return trim( $out );
	}

	private function is_whitespace( string $char ): bool {
		return ' ' === $char || "\t" === $char || "\n" === $char || "\r" === $char || "\f" === $char || "\v" === $char;
	}

	/**
	 * Return the index just past the closing quote of a string starting at $i.
	 */
	private function scan_string( string $css, int $i, string $quote ): int {
		$length = strlen( $css );
		$j      = $i + 1;

		while ( $j < $length ) {
			if ( '\\' === $css[ $j ] ) {
				$j += 2;
				continue;
			}

			if ( $quote === $css[ $j ] ) {
				return $j + 1;
			}

			$j++;
		}

		return $length;
	}

	private function is_url_start( string $css, int $i ): bool {
		return 0 === strncasecmp( substr( $css, $i, 4 ), 'url(', 4 );
	}

	/**
	 * Return the index just past the closing parenthesis of a url(...) token.
	 */
	private function scan_url( string $css, int $i ): int {
		$length = strlen( $css );
		$depth  = 0;
		$j      = $i + 3; // Points at the "(".

		while ( $j < $length ) {
			$char = $css[ $j ];

			if ( '"' === $char || "'" === $char ) {
				$j = $this->scan_string( $css, $j, $char );
				continue;
			}

			if ( '(' === $char ) {
				$depth++;
			} elseif ( ')' === $char ) {
				$depth--;

				if ( 0 === $depth ) {
					return $j + 1;
				}
			}

			$j++;
		}

		return $length;
	}

	/**
	 * Final guard: refuse to emit output that became empty, injected a closing
	 * tag, or unbalanced the original brace structure.
	 */
	private function is_safe( string $original, string $minified ): bool {
		if ( '' === trim( $minified ) ) {
			return false;
		}

		if ( false !== stripos( $minified, '</style' ) || false !== stripos( $minified, '</script' ) ) {
			return false;
		}

		return $this->is_balanced( $original ) && $this->is_balanced( $minified );
	}

	/**
	 * Whether braces balance once comments and strings are ignored.
	 */
	private function is_balanced( string $css ): bool {
		$stripped = preg_replace( '#/\*.*?\*/#s', '', $css );
		$stripped = preg_replace( '#"(?:\\\\.|[^"\\\\])*"#s', '""', (string) $stripped );
		$stripped = preg_replace( '#\'(?:\\\\.|[^\'\\\\])*\'#s', "''", (string) $stripped );

		return substr_count( (string) $stripped, '{' ) === substr_count( (string) $stripped, '}' );
	}
}
