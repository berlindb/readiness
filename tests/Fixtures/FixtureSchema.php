<?php
/**
 * @package BerlinDB\Readiness\Tests
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

namespace BerlinDB\Readiness\Tests\Fixtures;

/**
 * A stand-in Schema whose `columns` mirror a real one - including a `relationships`
 * column with a nested query/column config and a separate `indexes` property - so a
 * collector can be checked for leaking nested keys (the flaw a text scan hits).
 */
class FixtureSchema {

	/**
	 * @var array<int,array<string,mixed>>
	 */
	public $columns = array(
		array(
			'name'     => 'id',
			'type'     => 'bigint',
			'length'   => '20',
			'primary'  => true,
			'sortable' => true,
		),
		array(
			'name'          => 'author_id',
			'type'          => 'bigint',
			'length'        => '20',
			'in'            => true,
			'relationships' => array(
				array(
					// These nested keys must NOT be counted as flags.
					'query'  => 'Some\\Related\\Query',
					'column' => 'id',
				),
			),
		),
	);

	/**
	 * Index config the collector must ignore entirely (its `columns` key is not a flag).
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public $indexes = array(
		array(
			'type'    => 'key',
			'columns' => array( 'author_id' ),
		),
	);
}
