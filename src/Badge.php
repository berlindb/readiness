<?php
/**
 * Shields.io endpoint badge emitter.
 *
 * @package BerlinDB\Readiness
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness;

/**
 * Renders a {@see Report} as a shields.io endpoint-badge payload.
 *
 * A repo's CI writes this JSON to a committed file (e.g. `.readiness/edd.json`); its
 * README references it with
 * `https://img.shields.io/endpoint?url=<raw-json-url>`, so the badge updates on every
 * CI run with no external service or secret. See the shields endpoint schema:
 * https://shields.io/badges/endpoint-badge
 */
final class Badge {

	/**
	 * Build the shields endpoint payload for a report.
	 *
	 * Colour tracks readiness: 100% brightgreen, >=90% green, >=75% yellowgreen,
	 * >=50% yellow, else orange/red - so a regression is visible at a glance.
	 *
	 * @param Report $report The scored report.
	 * @param string $label  Optional badge label. Defaults to "<consumer> readiness".
	 * @return array{schemaVersion: int, label: string, message: string, color: string}
	 */
	public static function fromReport( Report $report, string $label = '' ): array {

		$percent = $report->percent();

		return array(
			'schemaVersion' => 1,
			'label'         => ( '' !== $label ) ? $label : $report->consumer() . ' readiness',
			'message'       => self::format_percent( $percent ),
			'color'         => self::color( $percent ),
		);
	}

	/**
	 * Serialize a report to a shields endpoint JSON string.
	 *
	 * @param Report $report The scored report.
	 * @param string $label  Optional badge label.
	 * @return string Pretty-printed JSON (newline-terminated).
	 */
	public static function toJson( Report $report, string $label = '' ): string {
		return json_encode( self::fromReport( $report, $label ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
	}

	/**
	 * Format a percentage as a badge message (no trailing `.0`).
	 *
	 * @param float $percent Readiness percentage.
	 */
	private static function format_percent( float $percent ): string {
		$whole = ( floor( $percent ) === $percent )
			? (string) (int) $percent
			: (string) $percent;

		return $whole . '%';
	}

	/**
	 * Pick a shields colour for a readiness percentage.
	 *
	 * @param float $percent Readiness percentage.
	 */
	private static function color( float $percent ): string {
		if ( $percent >= 100 ) {
			return 'brightgreen';
		}
		if ( $percent >= 90 ) {
			return 'green';
		}
		if ( $percent >= 75 ) {
			return 'yellowgreen';
		}
		if ( $percent >= 50 ) {
			return 'yellow';
		}

		return 'orange';
	}
}
