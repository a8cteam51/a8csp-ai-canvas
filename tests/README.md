# Tests

The test rig has four PHPUnit suites, a Jest suite for the JavaScript, and a Playwright end-to-end
suite. The PHPUnit and end-to-end suites run against wp-env fixtures at three WordPress-version
tiers.

## Suites

- **Unit** (`tests/Unit/`) — no WordPress, no wp-env. Runs against plain PHPUnit `TestCase` with
  recording `add_action()`/`add_filter()` stubs (`tests/Unit/wp-hook-stubs.php`) instead of Mockery
  or Brain Monkey, so the real `Plugin::boot()` loop is exercised outside WordPress.
  Fast, and part of `composer quality-check`.
- **Integration** (`tests/Integration/`) — boots inside wp-env against a supported WordPress
  version and exercises the plugin's real boot path. `HtmlBlockTest` covers the Custom HTML block's
  full-width alignment and editor assets, and `WebMcpTest` that the tools script loads in the page
  editor only. `UninstallTest` runs the real
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
- **JavaScript** (`tests/JavaScript/`) — Jest, with no WordPress and no wp-env. It covers how the
  tools join, split and check a page's content, and the parts of the visual editor that decide what
  gets saved: the tokenizer, the rewriting of the block's source, the pairing of the source with the
  parsed elements, the formatting of text, and what a click selects. Jest's DOM parses HTML to the
  standard, but it lays nothing out and has no caret: what depends on those is in the end-to-end
  suite.
- **End-to-End** (`tests/EndToEnd/`) — Playwright, driving a real browser against the dev wp-env
  instance. `visual-editor.spec.js` deletes only the page it creates, so it can be pointed at a
  site that has content of its own (`WP_BASE_URL`, `WP_USERNAME`, `WP_PASSWORD`); the other specs
  clear what they touch wholesale, and are for the wp-env instance only.

## Running the suites

Each of the wp-env-backed verbs — `test:integration`, `test:requirements` and `test:multisite` —
starts its own environment first: a container already running serves the
mount set it was created with, and `start` is what replaces it when the resolved config moved.
`test:unit` needs no container and starts none.

Unit (no wp-env required):

```sh
composer test:unit
```

JavaScript (no wp-env required):

```sh
npm run test:unit
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
| Dev / E2E   | `.wp-env.json`            | 28567 |
| Tests       | `.wp-env.tests.json`      | 28564 |
| Below-floor | `.wp-env.belowfloor.json` | 28565 |
| Multisite   | `.wp-env.multisite.json`  | 28566 |

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
