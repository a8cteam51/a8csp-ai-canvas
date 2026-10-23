# Architecture

This is the map of the plugin: what each file owns, how the pieces boot, and the recipes for
reshaping them.

## The component model

A plugin is a list of components; a component is a class with a static `should_load()` gate, an
`initialize()` readiness phase, and a `register_hooks()` attachment phase; the boot is a few
foreach loops you can read — gate and construct, initialize all, then register all hooks, so
every surviving component is initialized before any hook can fire.

## The map

- `a8csp-template-plugin.php` defines the plugin header and constants, requires
  `functions-bootstrap.php`, and wires the self-updater, the requirements gate, and the plugin
  boot.
- `functions-bootstrap.php` provides the GitHub release updater, plugin metadata,
  version-compatibility checks, the requirements gate, and its admin-notice reporter; both root
  bootstrap files stay parsable below the plugin's PHP floor, and CI lints them against the older
  PHP versions and runs the entry file there to prove the requirements gate stops at its notice.
- `functions.php` provides the construction-only plugin accessor (booting stays tied to the
  `plugins_loaded` attachment in the entry file) and loads the PHP helper files under `includes/`.
- `src/` follows one folder per feature, each owning a `Component` that composes it; the `src/`
  root holds only the bootstrapping mechanism. `src/ComponentInterface.php` is the one contract,
  and `src/Plugin.php` is the one file to edit when adding components to `COMPONENTS`; they boot
  in registration order. `src/ComponentCollection.php` is the shared gated collection both the
  composition root and group roots delegate their gate-construct and phase loops to — has-a, not
  is-a: the collection does not implement the contract. `src/AbstractComponent.php` is the
  optional defaults-only base (open gate, no-op readiness) for components that need neither. The
  plugin is a WooCommerce extension, so `boot()` opens with the plugin-wide host gate: without
  WooCommerce at the header-declared floor it stages an explanatory notice and stays un-booted.
- `src/Settings/` is the settings example feature and owns both settings surfaces: it persists
  `a8csp_template_example_option` through the Settings API on the General options page, registers
  a section in WooCommerce → Settings → Advanced persisting `a8csp_template_wc_example_option` —
  the host gate guarantees WooCommerce, so neither surface carries a gate — and demonstrates the
  uninstall footprint plus the compiled admin stylesheet (`assets/css/`).
- `src/Integrations/` groups the optional integrations behind one nested example:
  `src/Integrations/Component.php` is the group root that gates, constructs, and initializes its
  children inside its own phases. Its children model the two child shapes:
  `src/Integrations/WooPayments.php` is the single-class leaf — gated on WooPayments and hooking
  one of its payment-metadata filters — and `src/Integrations/WooCommerceSubscriptions/` is the
  grown sub-feature folder owning its own `Component` plus a plain collaborator, still
  forwarding-depth one. More nesting than this is the signal a plugin has outgrown manual
  composition.
- `includes/` contains automatically loaded procedural helpers, including typed option readers;
  PHP files dropped there load automatically inside WordPress, while underscore-prefixed files
  are skipped.
- `languages/` holds the plugin's translations; `composer i18n:makepot` generates the POT from the
  plugin's strings, and every release regenerates it.
- `models/` is the extension point for namespace-less classmapped classes that are part of a
  public contract — the `WC_Order` shape.
- `templates/` is an extension point for template partials rendered by components.
- `footprint.php` is the dependency-free manifest of the complete persisted footprint — every
  option and user-meta key grouped by owning component — and `uninstall.php` requires it and
  deletes those keys during WordPress's cold uninstall bootstrap; add an entry with each
  corresponding write, and cross-reference persisted keys with `@see footprint.php` in the
  component class docblock.
- `blocks/src/example-notice/` contains the example block source, while `blocks/build/` contains
  tracked build output; `npm run build` generates the committed `blocks/build/blocks-manifest.php`,
  which `src/Blocks/Component.php` uses to register all built blocks as one metadata collection.
  Both `build:blocks` and `start:blocks` pass `--blocks-manifest`: the first compile empties
  `blocks/build/`, so a watcher without the flag deletes the manifest and every block with it.
- `assets/js/src/editor.js` defines the shared editor hook entry point, and
  `assets/css/src/settings.scss` the compiled admin stylesheet example; `npm run build` is the
  single build contract and produces every tracked `build/` output, RTL variants included.
- `tests/` contains the automated test suite; see `tests/README.md` for the local workflow.
- `.github/workflows/` contains the CI workflows.

## Multisite

The plugin is expected to support multisite networks and to be tested on one when the site runs
one. Multisite is a design consideration while building, not a porting step at the end — when
adding a component, decide its scope deliberately:

- Options are per-site; user meta is network-global. `uninstall.php` models the consequence:
  its options sweep visits every site of a network in bounded batches, while its user-meta pass
  runs once.
- The requirements gate reports through `all_admin_notices`, which fires on site and network
  admin screens alike, so a failed network activation is explained where it happened.
- The plugin registers no activation or deactivation hooks. A plugin that adds them must
  handle the `$network_wide` activation flag and provision sites created after network
  activation (`wp_initialize_site`).
- The host gate and the component `should_load()` gates run on every request, so per-site
  environmental differences — such as WooCommerce or a companion being active on only some
  sites — resolve correctly site by site.
- The multisite wp-env fixture (`.wp-env.multisite.json`) converts itself into a network on
  start, and `composer test:multisite` proves the uninstall sweep against it.

## Growing the plugin

Optional integrations live behind the `src/Integrations/` group root
(`src/Integrations/Component.php`), which gates, constructs, and initializes its children inside
its own phases; add one child component per companion plugin — a single-class leaf at first,
promoted to its own folder the day it needs a second class.

## Watering down to plain WordPress

To convert this plugin from a WooCommerce extension to a plain WordPress plugin, remove the
WooCommerce tier:

1. Delete the `HELPERS` region — the host-requirements check and its notice closures — and the
   host gate in `boot()` from `src/Plugin.php`.
2. Strip the WooCommerce surface from `src/Settings/Component.php`: the `add_section()` and
   `provide_settings()` methods and their two filter registrations in `register_hooks()`.
3. Delete the `src/Integrations/` and `templates/myaccount/` directories — both example children
   extend the WooCommerce ecosystem — and remove `Integrations\Component::class` from the
   `COMPONENTS` list in `src/Plugin.php`.
4. Remove the `a8csp_template_wc_example_option` and `a8csp_template_wcs_example_option` option
   lines from the `footprint.php` manifest.
5. Remove the WooCommerce development dependency: `composer remove --dev wp-plugin/woocommerce`.
6. Remove the `wp-content/plugins/woocommerce` mapping from all four `.wp-env*.json` files, drop
   `woocommerce` from the `afterStart` plugin activations in `.wp-env.json`, `.wp-env.tests.json`
   and `.wp-env.multisite.json`, and remove the `extra-plugins` input (and its comment) from
   `.github/workflows/release.yml`.
7. Remove the WooCommerce `scanDirectories` entry from `.phpstan.neon`.
8. Remove the `before_woocommerce_init` compatibility block from the plugin entry file, and the
   `WC requires at least` / `WC tested up to` plugin-header lines.
9. Delete `tests/Integration/PluginBootWithoutWooCommerceTest.php`,
   `tests/Unit/IntegrationsComponentTest.php`,
   `tests/Unit/WooCommerceSubscriptionsComponentTest.php`, `tests/Unit/WooPaymentsTest.php`, and
   their `tests/Unit/wcs-stubs.php` / `tests/Unit/wcpay-stubs.php` stand-ins; drop the
   WooCommerce assertions and stand-ins from `tests/Integration/PluginBootTest.php`,
   `tests/Unit/ComponentCollectionTest.php`, `tests/Unit/PluginBootGateTest.php`, and
   `tests/Unit/SettingsComponentTest.php` (including `tests/Unit/wc-host-stubs.php`). That takes
   `PluginBootGateTest`'s two host-gate tests with their notice helpers and the closed-gate case
   of `ComponentCollectionTest`'s gate test.
10. Remove the WooCommerce-less proof section and the other `test:integration:no-wc` mentions
    from `tests/README.md`, the
    `test:integration:no-wc` scripts from `package.json` and `composer.json`, and the
    `integration-no-wc` job from `.github/workflows/tests.yml`.
11. Rewrite the WooCommerce-flavored prose: the Requirements section and the settings sentence
    under Installation in `README.md`, the WooCommerce references in this document, and whatever
    else `git grep -i woocommerce` still finds, such as the bug-report issue template.
12. Run `composer quality-check`. What remains — blocks, settings, the component list, the
    `includes/` loader, and a live uninstall footprint — is a complete plain WordPress plugin.

**For a plugin that persists nothing:**

1. Delete `src/Settings/` and `includes/settings.php`, and remove `Settings\Component::class` from
   the `COMPONENTS` list in `src/Plugin.php`.
2. Remove the Subscriptions example setting, which lives in the Settings section: the
   `add_settings()` method and its `woocommerce_get_settings_advanced` filter in
   `src/Integrations/WooCommerceSubscriptions/Component.php`.
3. Empty the `options` list in the `footprint.php` manifest (`'options' => array(),`).
   `uninstall.php` still clears the update-check transients; the uninstall proofs then cover only
   those transients and their canary, until the first persisted key returns.
4. Delete the example admin stylesheet (`assets/css/src/settings.scss` and its `assets/css/build/`
   output), the `build:assets:styles`, `build:assets:styles-rtl` and `start:assets:styles`
   scripts in `package.json`, and the `sass`, `postcss-cli` and `rtlcss` dev dependencies and
   `rtlcssConfig` key that serve only those scripts; run `npm install`.
5. Delete `tests/Unit/SettingsComponentTest.php`. Drop `Settings\Component` and the assertions on
   its hooks (`admin_init`, `admin_enqueue_scripts`, `woocommerce_get_sections_advanced` and
   `woocommerce_get_settings_advanced`) from `tests/Unit/ComponentCollectionTest.php`, whose gate
   test takes `Blocks\Component` as its open-gate case instead, and from
   `tests/Unit/PluginBootGateTest.php`; the `add_settings()` tests and filter assertions from
   `tests/Unit/WooCommerceSubscriptionsComponentTest.php` and
   `tests/Unit/IntegrationsComponentTest.php`; and, from `tests/Integration/PluginBootTest.php`
   and `tests/Integration/PluginBootWithoutWooCommerceTest.php`, the settings and section
   assertions, the round-trip test, and the `tearDown()` cleanup.
6. Rewrite the prose the removals leave false, which
   `git grep -n -i -E 'settings|example_option|option reader|footprint'` finds: the settings
   sentence under Installation in `README.md`, the settings steps and examples in
   `.github/ISSUE_TEMPLATE/bug_report.yml`, the settings, stylesheet and option-reader entries in
   this document, the uninstall-proof descriptions in `tests/README.md` and the test docblocks,
   and the docblocks of the Subscriptions component and `PluginBootTest`.
7. Run `composer quality-check`.

## Version tags on transplant

Every PHP file here carries `@since` and `@version` in its docblocks, because this plugin ships
from tagged releases and a reader can place any member against a published version. A component
with no release channel — a continuously deployed mu-plugin, a single site's own theme — has
nothing for the pair to name, so it omits them repo-wide rather than running two conventions side
by side. Moving a file into such a repository drops the pair; moving one back out restores it.
