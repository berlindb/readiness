<?php
/**
 * Relationship / meta capability scorer.
 *
 * @package BerlinDB\Readiness
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness;

/**
 * Scores a consumer's curated relationship / meta capability matrix against core.
 *
 * A consumer's relationships and meta are imperative (hand-coded JOINs, separate meta
 * tables), so unlike column flags there is nothing to auto-scan. Instead each consumer
 * curates a matrix: one entry per pattern it relies on, naming the core feature that
 * would express it. This scorer marks each entry `supported` (core provides that
 * feature) or a `gap` (it does not), so the matrix is a reviewable inventory AND a drift
 * guard - if core drops a feature a mapped entry relies on, the entry becomes a gap.
 *
 * A matrix entry is an array: `array{name: string, requires: string, kind?: string,
 * note?: string}`. `requires` is a {@see CoreFeatures} key (e.g. `relationship.has_many`).
 */
final class CapabilityReadiness {

	/**
	 * Score a consumer's capability matrix against core's supported features.
	 *
	 * @param string                    $consumer Consumer label.
	 * @param list<string>              $features Core feature keys (from {@see CoreFeatures::fromCore()}).
	 * @param list<array<string,mixed>> $matrix   The curated capability entries.
	 * @return Report
	 */
	public static function score( string $consumer, array $features, array $matrix ): Report {

		$rows = array();

		foreach ( $matrix as $entry ) {

			// Skip malformed entries rather than miscount them.
			if ( ! is_array( $entry ) || empty( $entry['name'] ) || ! is_string( $entry['name'] ) ) {
				continue;
			}

			$name     = $entry['name'];
			$requires = ( isset( $entry['requires'] ) && is_string( $entry['requires'] ) )
				? $entry['requires']
				: '';

			$rows[ $name ] = array(
				'status'  => in_array( $requires, $features, true ) ? Report::SUPPORTED : Report::GAP,
				'via'     => $requires,
				'columns' => 1,
			);
		}

		return new Report( $consumer, $rows );
	}
}
