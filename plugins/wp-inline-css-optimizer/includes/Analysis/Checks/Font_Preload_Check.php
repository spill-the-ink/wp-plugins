<?php
/**
 * Suggests preloading self-hosted fonts when none are preloaded.
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

class Font_Preload_Check implements Check {

	public function id(): string {
		return 'font-preload';
	}

	public function run( Context $context ): array {
		if ( $context->font_preload_present || 0 === $context->font_faces ) {
			return array();
		}

		$file_count = count( $context->font_files );

		$detail = $file_count > 0
			? sprintf(
				'%d @font-face rule(s) found; %d local woff2 file(s) detected, none preloaded.',
				$context->font_faces,
				$file_count
			)
			: sprintf( '%d @font-face rule(s) found; no font preload hint present.', $context->font_faces );

		return array(
			new Finding(
				$this->id(),
				Finding::OPPORTUNITY,
				'Self-hosted fonts are not preloaded.',
				$detail,
				'Preload the above-the-fold font via the wp_preload_resources filter (as="font", type="font/woff2", crossorigin).'
			),
		);
	}
}
