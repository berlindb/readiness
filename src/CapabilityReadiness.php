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
 * scope?: string, note?: string}`. `requires` is a {@see CoreFeatures} key (e.g.
 * `relationship.has_many`). `scope` is `behavioral` (only affects whether the plugin's
 * QUERIES run), `modeling` (only affects whether the schema can be MODELED with faithful
 * relationships), or `both` (default) - so one matrix yields two honest scores: a
 * behavioral badge and a modeling badge.
 */
final class CapabilityReadiness {

	/**
	 * Score a consumer's capability matrix against core's supported features.
	 *
	 * @param string                    $consumer Consumer label.
	 * @param list<string>              $features Core feature keys (from {@see CoreFeatures::fromCore()}).
	 * @param list<array<string,mixed>> $matrix   The curated capability entries.
	 * @param string|null               $scope    Restrict to entries in this dimension ('behavioral' |
	 *                                             'modeling'); null scores every entry. An entry with no
	 *                                             `scope` counts as `both`, so it is always included.
	 * @return Report
	 */
	public static function score( string $consumer, array $features, array $matrix, ?string $scope = null ): Report {

		$rows = array();

		foreach ( $matrix as $entry ) {

			// Skip malformed entries rather than miscount them.
			if ( ! is_array( $entry ) || empty( $entry['name'] ) || ! is_string( $entry['name'] ) ) {
				continue;
			}

			// Restrict to the requested dimension; a `both` (or absent) entry is always in scope.
			$entry_scope = ( isset( $entry['scope'] ) && is_string( $entry['scope'] ) ) ? $entry['scope'] : 'both';

			if ( ( null !== $scope ) && ( 'both' !== $entry_scope ) && ( $scope !== $entry_scope ) ) {
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
