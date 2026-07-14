<?php
/**
 * @package BerlinDB\Readiness\Tests
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness\Tests;

use BerlinDB\Readiness\SchemaSurface;
use BerlinDB\Readiness\Tests\Fixtures\FixtureSchema;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BerlinDB\Readiness\SchemaSurface
 */
final class SchemaSurfaceTest extends TestCase {

	public function test_counts_only_top_level_column_flags(): void {
		$declared = SchemaSurface::fromClasses( array( FixtureSchema::class ) );

		// Real flags, with per-column counts.
		$this->assertSame( 2, $declared['name'] );
		$this->assertSame( 2, $declared['type'] );
		$this->assertSame( 2, $declared['length'] );
		$this->assertSame( 1, $declared['primary'] );
		$this->assertSame( 1, $declared['sortable'] );
		$this->assertSame( 1, $declared['in'] );
		$this->assertSame( 1, $declared['relationships'] );
	}

	public function test_does_not_leak_nested_relationship_or_index_keys(): void {
		$declared = SchemaSurface::fromClasses( array( FixtureSchema::class ) );

		// The regression the naive text scan hit: nested relationship config
		// ('query', 'column') and index config ('columns') must never appear.
		$this->assertArrayNotHasKey( 'query', $declared );
		$this->assertArrayNotHasKey( 'column', $declared );
		$this->assertArrayNotHasKey( 'columns', $declared );
	}

	public function test_unknown_class_yields_no_surface(): void {
		$declared = SchemaSurface::fromClasses( array( 'No\\Such\\Schema' ) );

		$this->assertSame( array(), $declared );
	}

	public function test_missing_reports_classes_that_do_not_load(): void {
		$missing = SchemaSurface::missing( array( FixtureSchema::class, 'No\\Such\\Schema' ) );

		// The real class loads; the bogus one is reported so callers fail loudly
		// instead of scoring a phantom 100% off an empty surface.
		$this->assertSame( array( 'No\\Such\\Schema' ), $missing );
	}
}
