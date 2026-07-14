<?php
/**
 * @package BerlinDB\Readiness\Tests
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness\Tests;

use BerlinDB\Readiness\CoreCapabilities;
use BerlinDB\Readiness\FlagReadiness;
use BerlinDB\Readiness\Report;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BerlinDB\Readiness\FlagReadiness
 * @covers \BerlinDB\Readiness\Report
 */
final class FlagReadinessTest extends TestCase {

	/**
	 * EDD's real declared flag surface (flag => column count), from its fork schemas.
	 *
	 * @return array<string,int>
	 */
	private function edd_surface(): array {
		return array(
			'name' => 220, 'type' => 220, 'length' => 149, 'default' => 168,
			'unsigned' => 58, 'extra' => 17, 'primary' => 17, 'sortable' => 163,
			'searchable' => 45, 'allow_null' => 42, 'date_query' => 39,
			'transition' => 18, 'created' => 18, 'modified' => 18, 'uuid' => 15,
			'validate' => 13, 'cache_key' => 10, 'in' => 6, 'not_in' => 6,
			'compare' => 6, 'unique' => 1, 'primary_key' => 2, 'auto_increment' => 2,
		);
	}

	public function test_edd_is_fully_ready_against_current_core(): void {
		$report = FlagReadiness::score( 'EDD', CoreCapabilities::FALLBACK, $this->edd_surface() );

		$this->assertTrue( $report->is_ready() );
		$this->assertSame( array(), $report->gaps() );
		$this->assertSame( 100.0, $report->percent() );
		$this->assertSame( 23, $report->total() );
	}

	public function test_legacy_spellings_score_as_equivalent(): void {
		$report = FlagReadiness::score( 'EDD', CoreCapabilities::FALLBACK, $this->edd_surface() );
		$rows   = $report->rows();

		$this->assertSame( Report::EQUIVALENT, $rows['primary_key']['status'] );
		$this->assertSame( 'primary', $rows['primary_key']['via'] );
		$this->assertSame( Report::EQUIVALENT, $rows['auto_increment']['status'] );
		$this->assertSame( 'extra', $rows['auto_increment']['via'] );
	}

	public function test_missing_core_flag_is_reported_as_a_gap(): void {

		// Core BEFORE the `compare` flag landed: drop it from the supported set.
		$pre_compare = array_values( array_diff( CoreCapabilities::FALLBACK, array( 'compare' ) ) );

		$report = FlagReadiness::score( 'EDD', $pre_compare, $this->edd_surface() );

		$this->assertFalse( $report->is_ready() );
		$this->assertSame( array( 'compare' ), $report->gaps() );
		$this->assertSame( 95.7, $report->percent() );
		$this->assertSame( Report::GAP, $report->rows()['compare']['status'] );
		$this->assertSame( 6, $report->rows()['compare']['columns'] );
	}

	public function test_empty_surface_is_vacuously_ready(): void {
		$report = FlagReadiness::score( 'None', CoreCapabilities::FALLBACK, array() );

		$this->assertTrue( $report->is_ready() );
		$this->assertSame( 100.0, $report->percent() );
		$this->assertSame( 0, $report->total() );
	}

	public function test_rows_are_sorted_and_carry_column_counts(): void {
		$report = FlagReadiness::score( 'EDD', CoreCapabilities::FALLBACK, $this->edd_surface() );
		$rows   = $report->rows();

		$this->assertSame( array_keys( $rows ), array_values( array_unique( array_keys( $rows ) ) ) );
		$sorted = array_keys( $rows );
		$copy   = $sorted;
		sort( $copy );
		$this->assertSame( $copy, $sorted, 'rows are keyed in sorted flag order' );
		$this->assertSame( 163, $rows['sortable']['columns'] );
	}
}
