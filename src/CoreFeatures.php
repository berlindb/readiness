<?php
/**
 * Core relationship / meta feature collector.
 *
 * @package BerlinDB\Readiness
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness;

use ReflectionClass;
use Throwable;

/**
 * Gathers the relationship / meta features shared berlindb/core supports.
 *
 * Unlike column flags (declared config a Schema exposes), a consumer's relationships and
 * meta are usually IMPERATIVE - hand-coded JOINs and separate meta tables - so there is
 * nothing to auto-scan. Instead a curated per-consumer matrix ({@see CapabilityReadiness})
 * names each pattern and the core feature that would express it; this collector reports
 * which of those features core actually provides, so the matrix can be scored and a
 * dropped core feature turns a badge red.
 *
 * Feature keys (dotted): `relationship.belongs_to`, `relationship.has_many`,
 * `relationship.many_to_many`, `relationship.get_related`, `meta.store`, `meta.preset`.
 */
final class CoreFeatures {

	/**
	 * Documented fallback feature set (core's relationship/meta support as of 3.1.0).
	 *
	 * @var list<string>
	 */
	public const FALLBACK = array(
		'relationship.belongs_to',
		'relationship.has_many',
		'relationship.many_to_many',
		'relationship.get_related',
		'meta.store',
		'meta.preset',
	);

	/**
	 * Probe core for the relationship / meta features it provides.
	 *
	 * @param string $namespace Core's root namespace. Defaults to the canonical one.
	 * @return list<string> Supported feature keys.
	 */
	public static function fromCore( string $namespace = '\\BerlinDB\\Database' ): array {

		try {
			$features = array();

			// Relationship types are the private TYPES constant on the Relationship class.
			$relationship = "{$namespace}\\Kern\\Relationship";

			if ( class_exists( $relationship ) ) {
				$types = ( new ReflectionClass( $relationship ) )->getConstant( 'TYPES' );

				foreach ( (array) $types as $type ) {
					if ( is_string( $type ) ) {
						$features[] = "relationship.{$type}";
					}
				}
			}

			// The accessor that walks a relationship at read time.
			if ( method_exists( "{$namespace}\\Query", 'get_related' ) ) {
				$features[] = 'relationship.get_related';
			}

			// Custom meta store (a sibling table addressed as a meta relationship).
			if ( interface_exists( "{$namespace}\\Interfaces\\MetaStore" ) ) {
				$features[] = 'meta.store';
			}

			// The meta recipe a plugin extends for WordPress-style metadata.
			if ( class_exists( "{$namespace}\\Presets\\Meta\\Query" ) ) {
				$features[] = 'meta.preset';
			}

			return ( array() === $features )
				? self::FALLBACK
				: array_values( array_unique( $features ) );

		} catch ( Throwable ) {
			return self::FALLBACK;
		}
	}
}
