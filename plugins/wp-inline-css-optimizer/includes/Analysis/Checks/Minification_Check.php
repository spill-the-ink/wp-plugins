<?php
/**
 * Summarizes how much inline CSS the optimizer removed this request.
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

class Minification_Check implements Check {

	public function id(): string {
		return 'minification';
	}

	public function run( Context $context ): array {
		$in      = (int) $context->totals['bytes_in'];
		$out     = (int) $context->totals['bytes_out'];
		$handles = (int) $context->totals['handles'];

		if ( $handles < 1 || $in <= 0 ) {
			return array();
		}

		$saved   = max( 0, $in - $out );
		$percent = ( $saved / $in ) * 100;

		return array(
			new Finding(
				$this->id(),
				Finding::INFO,
				sprintf(
					'Minified %d handle(s): %s -> %s bytes (%s saved, %.1f%%).',
					$handles,
					number_format_i18n( $in ),
					number_format_i18n( $out ),
					number_format_i18n( $saved ),
					$percent
				)
			),
		);
	}
}
