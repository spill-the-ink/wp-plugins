<?php
/**
 * A single advisory finding produced by an Analysis check.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Finding {

	public const INFO        = 'info';
	public const NOTICE      = 'notice';
	public const OPPORTUNITY = 'opportunity';

	public string $id;
	public string $severity;
	public string $title;
	public string $detail;
	public string $suggestion;

	public function __construct(
		string $id,
		string $severity,
		string $title,
		string $detail = '',
		string $suggestion = ''
	) {
		$this->id         = $id;
		$this->severity   = self::normalize_severity( $severity );
		$this->title      = $title;
		$this->detail     = $detail;
		$this->suggestion = $suggestion;
	}

	/**
	 * Sort rank for a severity (lower sorts first).
	 */
	public static function rank( string $severity ): int {
		$order = array(
			self::OPPORTUNITY => 0,
			self::NOTICE      => 1,
			self::INFO        => 2,
		);

		$severity = self::normalize_severity( $severity );

		return $order[ $severity ];
	}

	private static function normalize_severity( string $severity ): string {
		if ( in_array( $severity, array( self::INFO, self::NOTICE, self::OPPORTUNITY ), true ) ) {
			return $severity;
		}

		return self::INFO;
	}
}
