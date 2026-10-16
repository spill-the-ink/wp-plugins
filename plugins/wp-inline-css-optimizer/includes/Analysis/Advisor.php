<?php
/**
 * Runs registered Analysis\Checks against a collected Context and formats the
 * resulting findings for the Developer mode report.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Advisor {

	private Collector $collector;

	/**
	 * Lazily-built default check set (shared across instances).
	 *
	 * @var Check[]|null
	 */
	private static ?array $default_checks = null;

	public function __construct( ?Collector $collector = null ) {
		$this->collector = $collector ?? new Collector();
	}

	/**
	 * Register the collector's hooks.
	 */
	public function setup(): void {
		$this->collector->setup();
	}

	/**
	 * The default checks, in a stable order.
	 *
	 * @return Check[]
	 */
	public static function default_checks(): array {
		if ( null === self::$default_checks ) {
			self::$default_checks = array(
				new Checks\Resource_Hints_Check(),
				new Checks\Preconnect_Check(),
				new Checks\Font_Preload_Check(),
				new Checks\Inline_Bypass_Check(),
				new Checks\Script_Strategy_Check(),
				new Checks\Render_Blocking_Styles_Check(),
				new Checks\Emoji_Check(),
				new Checks\Minification_Check(),
			);
		}

		return self::$default_checks;
	}

	/**
	 * Run every check and return the findings, sorted by severity.
	 *
	 * @param string[]             $processed_handles Handles the optimizer minified.
	 * @param array<string, mixed> $totals            Minification totals (bytes_in, bytes_out, handles).
	 * @return Finding[]
	 */
	public function analyze( array $processed_handles = array(), array $totals = array() ): array {
		$context = $this->collector->collect( $processed_handles, $totals );

		/**
		 * Filters the set of checks to run.
		 *
		 * @param Check[] $checks  Default checks.
		 * @param Context $context Collected context.
		 */
		$checks = (array) apply_filters( 'wp_inline_css_optimizer_advisor_checks', self::default_checks(), $context );

		$findings = array();

		foreach ( $checks as $check ) {
			if ( ! $check instanceof Check ) {
				continue;
			}

			foreach ( (array) $check->run( $context ) as $finding ) {
				if ( $finding instanceof Finding ) {
					$findings[] = $finding;
				}
			}
		}

		/**
		 * Filters the final advisory findings.
		 *
		 * @param Finding[] $findings Findings.
		 * @param Context   $context  Collected context.
		 */
		$findings = (array) apply_filters( 'wp_inline_css_optimizer_advisor_findings', $findings, $context );

		usort(
			$findings,
			static function ( Finding $a, Finding $b ): int {
				$rank = Finding::rank( $a->severity ) <=> Finding::rank( $b->severity );

				if ( 0 !== $rank ) {
					return $rank;
				}

				return strcmp( $a->id, $b->id );
			}
		);

		return $findings;
	}

	/**
	 * Format findings into report lines.
	 *
	 * @param Finding[] $findings Findings.
	 * @return string[]
	 */
	public static function format( array $findings ): array {
		$lines = array();

		foreach ( $findings as $finding ) {
			if ( ! $finding instanceof Finding ) {
				continue;
			}

			$lines[] = sprintf( '[%s] %s', $finding->severity, $finding->title );

			if ( '' !== $finding->detail ) {
				$lines[] = '    ' . $finding->detail;
			}

			if ( '' !== $finding->suggestion ) {
				$lines[] = '    -> ' . $finding->suggestion;
			}
		}

		return $lines;
	}
}
