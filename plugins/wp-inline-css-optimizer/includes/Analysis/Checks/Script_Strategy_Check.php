<?php
/**
 * Flags external head scripts that have no defer/async strategy.
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

class Script_Strategy_Check implements Check {

	public function id(): string {
		return 'script-strategy';
	}

	public function run( Context $context ): array {
		$count = 0;

		foreach ( $context->scripts as $script ) {
			if ( ! empty( $script['in_footer'] ) || empty( $script['external'] ) ) {
				continue;
			}

			if ( '' === trim( (string) ( $script['strategy'] ?? '' ) ) ) {
				$count++;
			}
		}

		if ( 0 === $count ) {
			return array();
		}

		return array(
			new Finding(
				$this->id(),
				Finding::NOTICE,
				sprintf( '%d external script(s) in the head have no defer or async strategy.', $count ),
				'Render-blocking scripts in the head delay first paint.',
				'Consider deferring scripts that do not need to run before render via wp_script_add_data( $handle, "strategy", "defer" ).'
			),
		);
	}
}
