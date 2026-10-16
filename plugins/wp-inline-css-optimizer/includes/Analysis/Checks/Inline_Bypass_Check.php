<?php
/**
 * Flags large inline CSS blocks that bypassed the optimizer.
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

class Inline_Bypass_Check implements Check {

	public function id(): string {
		return 'inline-bypass';
	}

	public function run( Context $context ): array {
		$findings = array();

		foreach ( $context->bypassed as $bypassed ) {
			$handle = (string) ( $bypassed['handle'] ?? '' );
			$bytes  = (int) ( $bypassed['bytes'] ?? 0 );

			if ( '' === $handle ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				Finding::OPPORTUNITY,
				sprintf( 'Inline CSS "%s" (%s bytes) was not minimized.', $handle, number_format_i18n( $bytes ) ),
				'It is printed outside the styles queue (for example hoisted global styles) or below the minimum size.',
				'Enable the head output-buffer fallback to catch it.'
			);
		}

		return $findings;
	}
}
