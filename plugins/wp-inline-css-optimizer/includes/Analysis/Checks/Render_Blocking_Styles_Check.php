<?php
/**
 * Reports how many render-blocking stylesheets the head loads.
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

class Render_Blocking_Styles_Check implements Check {

	/**
	 * Number of external stylesheets above which a notice is emitted.
	 */
	private const THRESHOLD = 4;

	public function id(): string {
		return 'render-blocking-styles';
	}

	public function run( Context $context ): array {
		$count = $context->external_style_count();

		if ( $count < self::THRESHOLD ) {
			return array();
		}

		return array(
			new Finding(
				$this->id(),
				Finding::INFO,
				sprintf( '%d render-blocking stylesheets are loaded in the head.', $count ),
				'External CSS blocks first paint.',
				'Consider whether any can be deferred or inlined (out of scope for this plugin).'
			),
		);
	}
}
