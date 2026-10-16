<?php
/**
 * A single developer-mode performance check.
 *
 * Implementations receive a fully collected {@see Context} and return zero or
 * more {@see Finding} objects. Checks must be pure with respect to the context
 * so they stay easy to unit test.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Check {

	/**
	 * Stable identifier (used by the check-registration filter).
	 */
	public function id(): string;

	/**
	 * Run the check.
	 *
	 * @return Finding[]
	 */
	public function run( Context $context ): array;
}
