<?php
/**
 * @package BerlinDB\Readiness\Tests
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness\Tests;

use BerlinDB\Readiness\Badge;
use BerlinDB\Readiness\CoreCapabilities;
use BerlinDB\Readiness\FlagReadiness;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BerlinDB\Readiness\Badge
 */
final class BadgeTest extends TestCase {

	public function test_full_readiness_is_brightgreen(): void {
		$report = FlagReadiness::score( 'EDD', CoreCapabilities::FALLBACK, array( 'sortable' => 3 ) );
		$badge  = Badge::fromReport( $report );

		$this->assertSame( 1, $badge['schemaVersion'] );
		$this->assertSame( 'EDD readiness', $badge['label'] );
		$this->assertSame( '100%', $badge['message'] );
		$this->assertSame( 'brightgreen', $badge['color'] );
	}

	public function test_a_gap_lowers_message_and_color(): void {
		$report = FlagReadiness::score( 'EDD', array( 'name' ), array( 'name' => 1, 'sortable' => 1 ) );
		$badge  = Badge::fromReport( $report );

		$this->assertSame( '50%', $badge['message'] );
		$this->assertSame( 'yellow', $badge['color'] );
	}

	public function test_custom_label_is_honored(): void {
		$report = FlagReadiness::score( 'EDD', CoreCapabilities::FALLBACK, array( 'sortable' => 1 ) );
		$badge  = Badge::fromReport( $report, 'reunification' );

		$this->assertSame( 'reunification', $badge['label'] );
	}

	public function test_json_is_valid_shields_endpoint_payload(): void {
		$report = FlagReadiness::score( 'EDD', CoreCapabilities::FALLBACK, array( 'sortable' => 1 ) );
		$json   = Badge::toJson( $report );

		$decoded = json_decode( $json, true );

		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'schemaVersion', $decoded );
		$this->assertArrayHasKey( 'label', $decoded );
		$this->assertArrayHasKey( 'message', $decoded );
		$this->assertArrayHasKey( 'color', $decoded );
		$this->assertStringEndsWith( "\n", $json );
	}
}
