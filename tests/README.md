# Tests

The test rig has four PHPUnit suites plus a Playwright end-to-end suite, run against wp-env
fixtures at three WordPress-version tiers.

## Suites

- **Unit** (`tests/Unit/`) — no WordPress, no wp-env. Runs against plain PHPUnit `TestCase` with
  recording `add_action()`/`add_filter()` stubs (`tests/Unit/wp-hook-stubs.php`) instead of Mockery
  or Brain Monkey, so the real `Plugin::boot()` loop is exercised outside WordPress.
  Fast, and part of `composer quality-check`.
- **Integration** (`tests/Integration/`) — boots inside wp-env against a supported WordPress
  version and exercises the plugin's real boot path. `UninstallTest` runs the real
  `uninstall.php` end-to-end (seeds sentinels, defines `WP_UNINSTALL_PLUGIN`, asserts its
  footprint is gone and a canary key survives) inside `#[RunInSeparateProcess]`, since that
  constant must not leak into the rest of the suite.
- **Requirements** (`tests/Integration/RequirementsCheckTest.php`, run as its own suite) — boots
  inside wp-env against a below-floor WordPress version to verify the requirements gate degrades
  gracefully instead of fataling.
- **Multisite** (`tests/Integration/MultisiteUninstallTest.php`, run as its own suite) — boots
  inside a wp-env fixture converted into a multisite network and proves `uninstall.php`'s options
  sweep cleans every site of the network, not just the site the uninstall runs on. The fixture is
  dedicated because `wp core multisite-convert` is one-way; the test self-skips when the
  Integration suite runs it against the single-site fixture.
- **End-to-End** (`tests/EndToEnd/`) — Playwright, driving a real browser against the dev wp-env
  instance.

## WooCommerce-less boot proof

`PluginBootWithoutWooCommerceTest` verifies the plugin-wide host gate: with WooCommerce inactive
the plugin stays un-booted and stages the "requires WooCommerce" notice instead of registering
anything. It self-skips whenever WooCommerce is active, so the proof needs WooCommerce deactivated
just for its run. One command does the whole dance, starting the tests wp-env instance first:

```sh
composer test:integration:no-wc
```

CI runs it as its own Tests job. `test:integration:no-wc` deactivates WooCommerce in the tests
wp-env instance, runs that one test directly with `--fail-on-skipped`, then reactivates
WooCommerce — failing when either the test or the reactivation fails — so the instance is left
ready for the Integration suite, and a run in which WooCommerce stayed active fails instead of
skipping. Its three steps are:

```sh
wp-env --config .wp-env.tests.json run cli wp plugin deactivate woocommerce
wp-env --config .wp-env.tests.json run cli --env-cwd=wp-content/plugins/a8csp-plugin-template vendor/bin/phpunit --testsuite=Integration --filter=PluginBootWithoutWooCommerceTest --fail-on-skipped
wp-env --config .wp-env.tests.json run cli wp plugin activate woocommerce
```

## Running the suites

Each of the wp-env-backed verbs — `test:integration`, `test:integration:no-wc`, `test:requirements`
and `test:multisite` — starts its own environment first: a container already running serves the
mount set it was created with, and `start` is what replaces it when the resolved config moved.
`test:unit` needs no container and starts none.

Unit (no wp-env required):

```sh
composer test:unit
```

Integration:

```sh
composer test:integration
npm run wp-env:tests:stop
```

Requirements:

```sh
composer test:requirements
npm run wp-env:belowfloor:stop
```

Multisite (its `afterStart` converts the fresh install into a subdirectory network):

```sh
composer test:multisite
npm run wp-env:multisite:stop
```

End-to-end (Playwright's `webServer` config starts the dev instance when none is running; stop it
with `npm run wp-env:stop`):

```sh
npm run test:e2e
```

## Ports

| Environment | Config                    | Port |
| ----------- | ------------------------- | ---- |
| Dev / E2E   | `.wp-env.json`            | 8893 |
| Tests       | `.wp-env.tests.json`      | 8890 |
| Below-floor | `.wp-env.belowfloor.json` | 8891 |
| Multisite   | `.wp-env.multisite.json`  | 8892 |

If another local environment already uses these ports, wp-env's untracked override files take
local precedence (`.wp-env.override.json`; custom configs pair with e.g.
`.wp-env.tests.override.json`).

## Why plain `TestCase`, not `WP_UnitTestCase`

WordPress core's PHPUnit scaffold supports PHPUnit through version 9. This rig runs current
PHPUnit directly against plain `TestCase` inside wp-env, without depending on `WP_UnitTestCase`
or core's PHPUnit compatibility range.

That trade gives up `$this->factory` fixture helpers, `go_to()` routing simulation, and
`WP_UnitTestCase`'s per-test transaction rollback. The first two exist for content- and
query-heavy plugins exercising post/term/user fixtures and template routing — this plugin's
Integration suite is narrower (boot path, requirements gating), so their absence costs little.
Without rollback, the database persists across tests: each test removes what it seeds, except that
the uninstall proofs leave their footprint to the `uninstall.php` run they assert on.

## Mutation testing

`composer test:unit:mutation` runs Infection against the Unit suite's source. It sits outside the
default `composer quality-check` target (only `quality-check:all` pulls it in) and does not gate
pull requests — it runs on its own weekly schedule in CI (`.github/workflows/tests-mutation.yml`),
since mutation testing is slow.
