<?php
/**
 * Flags WordPress emoji detection (script + inline styles) as removable.
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

class Emoji_Check implements Check {

	/**
	 * Handles owned by the core emoji feature.
	 */
	private const HANDLES = array( 'wp-emoji', 'wp-emoji-release', 'wp-emoji-styles' );

	public function id(): string {
		return 'emoji';
	}

	public function run( Context $context ): array {
		$present = false;

		foreach ( array_merge( $context->styles, $context->scripts ) as $item ) {
			if ( in_array( (string) ( $item['handle'] ?? '' ), self::HANDLES, true ) ) {
				$present = true;

				break;
			}
		}

		if ( ! $present ) {
			return array();
		}

		return array(
			new Finding(
				$this->id(),
				Finding::NOTICE,
				'WordPress emoji detection script and inline styles are loading.',
				'',
				'If emoji support is not needed, disabling it saves a request and some inline CSS.'
			),
		);
	}
}
