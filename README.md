# berlindb/readiness

**Can shared [berlindb/core](https://github.com/berlindb/core) faithfully express a
given plugin's database layer?** This package measures it - a *reunification readiness*
score per consumer - and renders it as a badge.

Structural parity (does core reproduce the exact `CREATE TABLE`?) is answered by each
consumer's own capability tests. This package answers the **behavioral** question: of
all the per-column *flags* a consumer declares (`sortable`, `searchable`, `in`,
`compare`, `date_query`, `transition`, `uuid`, relationships, ...), how many can shared
core recognize? A flag core cannot express is a **gap** - a concrete item on the path to
migrating that plugin onto shared core.

> **Scope (v1): column flags.** Relationships and meta are measured separately and are
> not in this version - first-generation forks express them as imperative SQL joins, not
> declared config, so they need a curated capability matrix rather than an automatic
> scan. See [Roadmap](#roadmap).

## What the score means (and does not)

Two kinds of consumer, and the honesty matters:

- **Fork consumers** (e.g. EDD, Sugar Calendar) vendor their *own* first-generation
  BerlinDB fork and declare flags independently of core. Scoring their fork against core
  is a **real, external yardstick**: a gap is a genuine expressiveness difference.
- **Parity consumers** (e.g. WordPress, WooCommerce) do not use BerlinDB; a parity plugin
  *authors* core-native schemas that reproduce their tables. Those schemas only use flags
  core already has, so they are **100% by construction** - the badge confirms the
  reproduction is complete, not that an independent surface happens to match.

A badge should carry that label so a parity 100% is not mistaken for a discovered result.

## Usage

Install as a dev dependency in a repo where core and the consumer's `Schema` classes are
autoloadable (e.g. a parity/capability repo's WP test environment):

```bash
composer require --dev berlindb/readiness
```

In a test or CI step, gather the surface and score it:

```php
use BerlinDB\Readiness\{CoreCapabilities, SchemaSurface, FlagReadiness, Badge};

$supported = CoreCapabilities::fromCore();                 // live, via reflection
$declared  = SchemaSurface::fromClasses( array(            // the consumer's schemas
    \EDD\Database\Schemas\Orders::class,
    \EDD\Database\Schemas\Customers::class,
    // ...
) );

$report = FlagReadiness::score( 'EDD', $supported, $declared );

$report->percent();   // 100.0
$report->gaps();      // array()  (['compare'] before core#... landed)
$report->is_ready();  // true

file_put_contents( '.readiness/edd.json', Badge::toJson( $report ) );
```

Or from the CLI (inside the same bootstrap):

```bash
vendor/bin/readiness --consumer=EDD \
  --schemas="EDD\\Database\\Schemas\\Orders,EDD\\Database\\Schemas\\Customers" \
  --json=.readiness/edd.json
```

The CLI exits non-zero when there is a gap, so CI fails loudly on a regression.

## Badges

`Badge::toJson()` emits a [shields.io endpoint](https://shields.io/badges/endpoint-badge)
payload. Commit it in CI, then point a badge at the raw file - no external service or
secret, and it refreshes on every run:

```markdown
![EDD readiness](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/berlindb/edd-core-tables/master/.readiness/edd.json)
```

Colour tracks the score (100% brightgreen, >=90% green, >=75% yellowgreen, >=50% yellow,
else orange), so a drop is visible at a glance.

## How it works

- `CoreCapabilities::fromCore()` - reflects `Column::get_config_callbacks()` on the
  installed core to read its recognized flag set live (falls back to a documented list).
- `SchemaSurface::fromClasses()` - reads each Schema's `columns` property default via
  reflection, counting only each column's **top-level** keys, so nested relationship /
  index config never leaks in as a phantom flag.
- `FlagReadiness::score()` - a pure function comparing the two, classifying each declared
  flag `supported` / `equivalent` (a legacy spelling core expresses under another name,
  e.g. `primary_key` -> `primary`) / `gap`, and returning an immutable `Report`.
- `Badge` - renders a `Report` as the shields payload.

The scorer is pure and unit-tested with plain arrays; the reflection collectors run
inside the consumer's bootstrap where the real classes are loaded.

## Roadmap

- **Relationships & meta.** A curated per-consumer capability matrix (order->items
  `has_many`, meta tables -> core meta store, ...), each mapped to a core feature and
  checked for existence - the faithful way to score an imperative surface.
- **Aggregate dashboard.** A combined view / wiki page across all consumers.

## License

GPL-2.0-or-later.
