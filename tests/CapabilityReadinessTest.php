<?php
/**
 * @package BerlinDB\Readiness\Tests
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness\Tests;

use BerlinDB\Readiness\CapabilityReadiness;
use BerlinDB\Readiness\CoreFeatures;
use BerlinDB\Readiness\FlagReadiness;
use BerlinDB\Readiness\Report;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BerlinDB\Readiness\CapabilityReadiness
 * @covers \BerlinDB\Readiness\CoreFeatures
 * @covers \BerlinDB\Readiness\Report::combine
 */
final class CapabilityReadinessTest extends TestCase {

	/**
	 * EDD's real relationship/meta matrix (grounded in its hand-coded queries).
	 *
	 * @return list<array<string,mixed>>
	 */
	private function edd_matrix(): array {
		return array(
			array( 'name' => 'order -> order_items',        'kind' => 'has_many', 'requires' => 'relationship.has_many' ),
			array( 'name' => 'order -> order_adjustments',  'kind' => 'has_many', 'requires' => 'relationship.has_many' ),
			array( 'name' => 'order -> order_transactions', 'kind' => 'has_many', 'requires' => 'relationship.has_many' ),
			array( 'name' => 'order -> order_addresses',    'kind' => 'has_many', 'requires' => 'relationship.has_many' ),
			array( 'name' => 'order_item -> order',         'kind' => 'belongs_to', 'requires' => 'relationship.belongs_to' ),
			array( 'name' => 'get_related() traversal',     'kind' => 'accessor', 'requires' => 'relationship.get_related' ),
			array( 'name' => 'ordermeta / customermeta',    'kind' => 'meta',     'requires' => 'meta.store' ),
		);
	}

	public function test_edd_matrix_is_fully_expressible_by_core(): void {
		$report = CapabilityReadiness::score( 'EDD', CoreFeatures::FALLBACK, $this->edd_matrix() );

		$this->assertTrue( $report->is_ready() );
		$this->assertSame( 100.0, $report->percent() );
		$this->assertSame( 7, $report->total() );
	}

	public function test_a_missing_core_feature_is_a_gap(): void {

		// Core WITHOUT many_to_many support, and a consumer that needs it.
		$features = array_values( array_diff( CoreFeatures::FALLBACK, array( 'relationship.many_to_many' ) ) );
		$matrix   = array(
			array( 'name' => 'products <-> categories', 'requires' => 'relationship.many_to_many' ),
		);

		$report = CapabilityReadiness::score( 'X', $features, $matrix );

		$this->assertFalse( $report->is_ready() );
		$this->assertSame( array( 'products <-> categories' ), $report->gaps() );
	}

	public function test_malformed_entries_are_skipped(): void {
		$matrix = array(
			array( 'name' => 'ok', 'requires' => 'meta.store' ),
			array( 'requires' => 'meta.store' ), // no name
			'nonsense',
		);

		$report = CapabilityReadiness::score( 'X', CoreFeatures::FALLBACK, $matrix );

		$this->assertSame( 1, $report->total() );
	}

	public function test_combine_folds_flags_and_matrix_into_one_score(): void {
		$flags  = FlagReadiness::score( 'EDD', array( 'name', 'sortable' ), array( 'name' => 2, 'sortable' => 5 ) );
		$matrix = CapabilityReadiness::score( 'EDD', CoreFeatures::FALLBACK, $this->edd_matrix() );

		$combined = Report::combine( 'EDD', $flags, $matrix );

		// 2 flags + 7 capabilities, all covered.
		$this->assertSame( 9, $combined->total() );
		$this->assertSame( 100.0, $combined->percent() );
		$this->assertTrue( $combined->is_ready() );
	}

	public function test_combine_surfaces_a_gap_from_either_dimension(): void {
		$flags  = FlagReadiness::score( 'X', array( 'name' ), array( 'name' => 1, 'exotic_flag' => 1 ) );
		$matrix = CapabilityReadiness::score( 'X', CoreFeatures::FALLBACK, $this->edd_matrix() );

		$combined = Report::combine( 'X', $flags, $matrix );

		$this->assertFalse( $combined->is_ready() );
		$this->assertContains( 'exotic_flag', $combined->gaps() );
	}

	public function test_scope_splits_behavioral_from_modeling(): void {
		$matrix = array(
			array( 'name' => 'join collection', 'requires' => 'relationship.has_many' ),               // both (default)
			array( 'name' => 'meta',            'requires' => 'meta.store' ),                            // both (default)
			array( 'name' => 'polymorphic',     'requires' => 'relationship.conditioned', 'scope' => 'modeling' ),
		);

		// Behavioral view excludes the modeling-only entry -> everything it scores is supported.
		$behavioral = CapabilityReadiness::score( 'X', CoreFeatures::FALLBACK, $matrix, 'behavioral' );
		$this->assertSame( 2, $behavioral->total() );
		$this->assertTrue( $behavioral->is_ready() );
		$this->assertSame( 100.0, $behavioral->percent() );

		// Modeling view includes it -> the missing feature is a gap.
		$modeling = CapabilityReadiness::score( 'X', CoreFeatures::FALLBACK, $matrix, 'modeling' );
		$this->assertSame( 3, $modeling->total() );
		$this->assertSame( array( 'polymorphic' ), $modeling->gaps() );

		// No scope scores every entry.
		$all = CapabilityReadiness::score( 'X', CoreFeatures::FALLBACK, $matrix );
		$this->assertSame( 3, $all->total() );
	}

	public function test_fallback_feature_set_is_non_empty(): void {
		$this->assertNotEmpty( CoreFeatures::FALLBACK );
		$this->assertContains( 'relationship.has_many', CoreFeatures::FALLBACK );
		$this->assertContains( 'meta.store', CoreFeatures::FALLBACK );
	}
}
