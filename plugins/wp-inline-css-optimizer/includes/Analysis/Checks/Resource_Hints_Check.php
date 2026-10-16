<?php
/**
 * Reports the resource hints already present in the document head.
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

class Resource_Hints_Check implements Check {

	public function id(): string {
		return 'resource-hints';
	}

	public function run( Context $context ): array {
		$relations = array( 'preconnect', 'dns-prefetch', 'preload', 'prefetch' );
		$parts     = array();

		foreach ( $relations as $relation ) {
			$count = count( $context->hints_for( $relation ) );

			if ( $count > 0 ) {
				$parts[] = sprintf( '%s: %d', $relation, $count );
			}
		}

		$title = $parts
			? 'Resource hints in use - ' . implode( ', ', $parts ) . '.'
			: 'No resource hints found (preconnect, dns-prefetch, preload, prefetch).';

		return array( new Finding( $this->id(), Finding::INFO, $title ) );
	}
}
