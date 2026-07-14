<?php
/**
 * Column-flag readiness scorer.
 *
 * @package BerlinDB\Readiness
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness;

/**
 * Scores a consumer's declared column-flag surface against core's recognized flags.
 *
 * "Flags" are the per-column keys a Schema declares (name / type / length plus the
 * behavioral flags sortable / searchable / in / compare / date_query / transition /
 * uuid / ...). Core's recognized set is the yardstick. A declared flag is `supported`
 * when core recognizes it verbatim, `equivalent` when it is a legacy spelling core
 * expresses under another (mapped) name, or a `gap` when core cannot express it.
 *
 * Pure: the scorer takes plain arrays, so it is engine-agnostic and unit-testable.
 * The reflection collectors ({@see CoreCapabilities}, {@see SchemaSurface}) gather the
 * arrays from a live core / consumer inside a bootstrap; this class does the scoring.
 */
final class FlagReadiness {

	/**
	 * Legacy / equivalent spellings: a consumer flag => the core flag it maps to.
	 *
	 * First-generation BerlinDB forks predate some of core's current spellings, e.g.
	 * `primary_key => true` (now `primary`) and `auto_increment => true` (now
	 * `extra => 'auto_increment'`). A mapped flag counts as covered, not a gap.
	 *
	 * @return array<string,string>
	 */
	public static function equivalence_map(): array {
		return array(
			'primary_key'    => 'primary',
			'auto_increment' => 'extra',
		);
	}

	/**
	 * Score a consumer's declared flags against core's recognized flags.
	 *
	 * @param string             $consumer  Consumer label (e.g. 'EDD').
	 * @param list<string>       $supported Core's recognized flag names.
	 * @param array<string,int>  $declared  Consumer flag => number of columns declaring it.
	 * @param array<string,string> $equivalence Optional override of the legacy-spelling map.
	 * @return Report
	 */
	public static function score( string $consumer, array $supported, array $declared, ?array $equivalence = null ): Report {

		$equivalence = $equivalence ?? self::equivalence_map();
		$supported   = array_values( array_unique( $supported ) );

		ksort( $declared );

		$rows = array();

		foreach ( $declared as $flag => $columns ) {
			$columns = (int) $columns;

			if ( in_array( $flag, $supported, true ) ) {
				$rows[ $flag ] = array(
					'status'  => Report::SUPPORTED,
					'via'     => $flag,
					'columns' => $columns,
				);

				continue;
			}

			if ( isset( $equivalence[ $flag ] ) && in_array( $equivalence[ $flag ], $supported, true ) ) {
				$rows[ $flag ] = array(
					'status'  => Report::EQUIVALENT,
					'via'     => $equivalence[ $flag ],
					'columns' => $columns,
				);

				continue;
			}

			$rows[ $flag ] = array(
				'status'  => Report::GAP,
				'via'     => '',
				'columns' => $columns,
			);
		}

		return new Report( $consumer, $rows );
	}
}
