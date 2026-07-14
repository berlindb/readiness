<?php
/**
 * Consumer schema surface collector.
 *
 * @package BerlinDB\Readiness
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness;

use ReflectionClass;
use Throwable;

/**
 * Gathers the declared column-flag surface of a consumer's Schema classes.
 *
 * Reads each Schema's `columns` property default via reflection - the raw declared
 * array, WITHOUT constructing the class (so no Boot lifecycle, sanitization, or
 * side effects run). Only each column's top-level keys are counted as flags, so the
 * nested `relationships` / `aliases` / index config values never leak in (the flaw a
 * naive text scan hits: counting a relationship's inner `query` / `column` as flags).
 */
final class SchemaSurface {

	/**
	 * Count declared flags across a set of consumer Schema classes.
	 *
	 * @param list<string> $schema_classes Fully-qualified Schema class names.
	 * @return array<string,int> flag => number of columns (across all classes) declaring it.
	 */
	public static function fromClasses( array $schema_classes ): array {

		$counts = array();

		foreach ( $schema_classes as $class ) {
			foreach ( self::columns_of( $class ) as $column ) {

				// Only well-formed column arrays contribute; each top-level key is a flag.
				if ( ! is_array( $column ) ) {
					continue;
				}

				foreach ( array_keys( $column ) as $flag ) {
					if ( is_string( $flag ) ) {
						$counts[ $flag ] = ( $counts[ $flag ] ?? 0 ) + 1;
					}
				}
			}
		}

		ksort( $counts );

		return $counts;
	}

	/**
	 * Which of the requested classes do not load (autoload attempted).
	 *
	 * A requested Schema that does not load yields no surface, which would silently
	 * understate the declared flags - and so understate gaps, a false "ready". Callers
	 * (the CLI, a repo's readiness test) use this to fail loudly instead: a missing
	 * class is a misconfiguration, not a clean score.
	 *
	 * @param list<string> $schema_classes Fully-qualified Schema class names.
	 * @return list<string> The subset that could not be loaded.
	 */
	public static function missing( array $schema_classes ): array {

		$missing = array();

		foreach ( $schema_classes as $class ) {
			if ( ! class_exists( $class ) ) {
				$missing[] = $class;
			}
		}

		return $missing;
	}

	/**
	 * Read a Schema class's declared `columns` array without constructing it.
	 *
	 * @param string $class Fully-qualified Schema class name.
	 * @return array<int,mixed> The declared columns (empty on any reflection failure).
	 */
	private static function columns_of( string $class ): array {

		try {
			$rc       = new ReflectionClass( $class );
			$defaults = $rc->getDefaultProperties();

			$columns = $defaults['columns'] ?? array();

			return is_array( $columns )
				? array_values( $columns )
				: array();

		} catch ( Throwable ) {
			return array();
		}
	}
}
