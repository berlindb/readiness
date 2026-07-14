<?php
/**
 * Core capability collector.
 *
 * @package BerlinDB\Readiness
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness;

use ReflectionClass;
use Throwable;

/**
 * Gathers the set of column flags shared berlindb/core recognizes.
 *
 * The authoritative source is Column::get_config_callbacks() - the keys of the map
 * core uses to sanitize declared column config. Read live via reflection so the score
 * always tracks the installed core, with a documented fallback for environments where
 * reflection is unavailable. Structural keys understood outside that map (name, type,
 * length) are always included.
 */
final class CoreCapabilities {

	/**
	 * Structural keys core understands outside the config-callback map.
	 *
	 * @var list<string>
	 */
	public const STRUCTURAL = array( 'name', 'type', 'length' );

	/**
	 * Documented fallback flag set (core's recognized flags as of 3.1.0).
	 *
	 * Used only when reflection cannot read a live Column. Kept in sync by the
	 * consumer repos' CI, which prefers {@see fromCore()} and would surface drift.
	 *
	 * @var list<string>
	 */
	public const FALLBACK = array(
		'name', 'type', 'length', 'scale', 'unsigned', 'zerofill', 'binary',
		'allow_null', 'default', 'extra', 'encoding', 'collation', 'comment',
		'primary', 'unique', 'index', 'created', 'modified', 'uuid',
		'searchable', 'sortable', 'date_query', 'transition', 'in', 'not_in',
		'compare', 'cache_key', 'pattern', 'type_category', 'cast', 'validate',
		'caps', 'aliases', 'relationships',
	);

	/**
	 * Read core's recognized flags live from a Column class via reflection.
	 *
	 * Invokes get_config_callbacks() on a construction-free instance (its key set does
	 * not depend on constructed state) and unions the keys with the structural set.
	 * Falls back to {@see FALLBACK} if reflection fails for any reason.
	 *
	 * @param string $column_class Fully-qualified core Column class. Defaults to the
	 *                             canonical berlindb/core Column.
	 * @return list<string> Recognized flag names.
	 */
	public static function fromCore( string $column_class = '\\BerlinDB\\Database\\Column' ): array {

		try {
			$rc = new ReflectionClass( $column_class );

			$instance = $rc->newInstanceWithoutConstructor();
			$method   = $rc->getMethod( 'get_config_callbacks' );

			/** @var array<string,mixed> $callbacks */
			$callbacks = (array) $method->invoke( $instance );

			$flags = array_merge( self::STRUCTURAL, array_keys( $callbacks ) );

			return array_values( array_unique( array_map( 'strval', $flags ) ) );

		} catch ( Throwable ) {
			return self::FALLBACK;
		}
	}
}
