<?php
/**
 * Readiness report value object.
 *
 * @package BerlinDB\Readiness
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness;

/**
 * The scored result of comparing a consumer's declared surface to core's capabilities.
 *
 * Immutable: {@see FlagReadiness::score()} builds one and callers read it. Each row
 * records one declared flag and how core covers it - `supported` (core recognizes the
 * same flag), `equivalent` (core expresses it under a different, mapped name), or
 * `gap` (core cannot express it).
 */
final class Report {

	public const SUPPORTED  = 'supported';
	public const EQUIVALENT = 'equivalent';
	public const GAP        = 'gap';

	/**
	 * @var string The consumer this report scores (e.g. 'EDD').
	 */
	private string $consumer;

	/**
	 * @var array<string,array{status: string, via: string, columns: int}> flag => row.
	 */
	private array $rows;

	/**
	 * @param string                                                        $consumer Consumer label.
	 * @param array<string,array{status: string, via: string, columns: int}> $rows     Scored rows, keyed by flag.
	 */
	public function __construct( string $consumer, array $rows ) {
		$this->consumer = $consumer;
		$this->rows     = $rows;
	}

	/**
	 * Merge several reports into one combined readiness report.
	 *
	 * Used to fold a consumer's separate dimensions - column flags and the curated
	 * relationship/meta matrix - into a single score and badge. Rows are keyed in
	 * distinct namespaces (flag names vs capability names), so a later report only
	 * overrides an earlier one on a genuine key clash.
	 *
	 * @param string $consumer Consumer label for the merged report.
	 * @param Report ...$reports Reports to merge, in order.
	 * @return Report
	 */
	public static function combine( string $consumer, Report ...$reports ): Report {

		$rows = array();

		foreach ( $reports as $report ) {
			foreach ( $report->rows() as $key => $row ) {
				$rows[ $key ] = $row;
			}
		}

		return new Report( $consumer, $rows );
	}

	/**
	 * The consumer label.
	 */
	public function consumer(): string {
		return $this->consumer;
	}

	/**
	 * All scored rows, keyed by flag.
	 *
	 * @return array<string,array{status: string, via: string, columns: int}>
	 */
	public function rows(): array {
		return $this->rows;
	}

	/**
	 * The flags core cannot express (status `gap`).
	 *
	 * @return list<string>
	 */
	public function gaps(): array {
		$gaps = array();

		foreach ( $this->rows as $flag => $row ) {
			if ( self::GAP === $row['status'] ) {
				$gaps[] = $flag;
			}
		}

		return $gaps;
	}

	/**
	 * Total distinct flags the consumer declares.
	 */
	public function total(): int {
		return count( $this->rows );
	}

	/**
	 * Flags core covers (supported + equivalent).
	 */
	public function covered(): int {
		return $this->total() - count( $this->gaps() );
	}

	/**
	 * Readiness percentage (100.0 when the consumer declares nothing to cover).
	 */
	public function percent(): float {
		$total = $this->total();

		return ( 0 === $total )
			? 100.0
			: round( 100 * $this->covered() / $total, 1 );
	}

	/**
	 * Whether core fully covers this consumer's declared surface.
	 */
	public function is_ready(): bool {
		return array() === $this->gaps();
	}
}
