<?php
/**
 * Suggests preconnect/dns-prefetch for cross-origin hosts used without a hint.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Analysis\Checks;

use WpInlineCssOptimizer\Analysis\Check;
use WpInlineCssOptimizer\Analysis\Context;
use WpInlineCssOptimizer\Analysis\Finding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Preconnect_Check implements Check {

	public function id(): string {
		return 'preconnect';
	}

	public function run( Context $context ): array {
		$hinted   = array_merge( $context->hints_for( 'preconnect' ), $context->hints_for( 'dns-prefetch' ) );
		$findings = array();

		foreach ( $context->external_hosts() as $host ) {
			if ( $context->host_is_hinted( $host, $hinted ) ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				Finding::OPPORTUNITY,
				sprintf( 'External origin "%s" is used without a preconnect or dns-prefetch hint.', $host ),
				'',
				sprintf( 'If assets from %s are needed early, add a preconnect (or dns-prefetch) via the wp_resource_hints filter.', $host )
			);
		}

		return $findings;
	}
}
