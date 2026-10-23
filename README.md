# A8CSP Template Plugin

A template for A8C Special Projects WordPress plugins.

It holds the PHP bootstrap, block and asset builds, test suites, and CI that a new plugin
repository starts from.

## Generating a new plugin repository

A repository created from this template runs `.github/workflows/fill-in-scaffold.yml` from its
default branch, on a `fill_scaffold` repository dispatch or manually. It reads the repository name,
the description, and the `human-title` and `php-globals-short-prefix` custom properties, then
renames the entry file, README, title, slug, text domain, Composer package, namespace, and PHP
prefix, derives a per-repository wp-env port block, resolves both lockfiles afresh, and deletes
itself. The `strip-teaching-content` input also rewrites the teaching prose into production
docblocks. The example block copy, settings labels, and option keys are yours to rename.

## Working on the template

The template never versions itself: a repository ruleset blocks tags, `release.yml` skips its
release job here, and `template-guard.yml` stops pull requests from adding changelog fragments or
bumping the version. The changelog machinery, `CHANGELOG.md`, and `release.yml` are payload for
generated plugins.

[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) maps every file and holds the reshaping recipes,
`README.scaffold.md` becomes a generated plugin's README, and [`tests/README.md`](tests/README.md)
covers the test suites. The teaching-content strip matches each passage in
`fill-in-scaffold-content.mjs` by its exact text, and `template-guard.yml` fails any change that
leaves a passage matching zero or several times.

### Requirements

The plugin header, `composer.json`, `.wp-env.json`, and `package.json`'s `engines` declare the
WordPress, WooCommerce, PHP, Node.js, and npm versions; wp-env also needs Docker.

### Development

Install PHP dependencies:

```sh
composer run-script packages-install
```

Install JavaScript dependencies:

```sh
npm ci
```

Build blocks and editor assets:

```sh
npm run build
```

Run watch builds:

```sh
npm start
```

`npm start` watches blocks, scripts, and Sass, but for `assets/css` the watcher runs Sass only — the PostCSS vendor-prefix pass and the RTL stylesheet come from `npm run build`, so that watched CSS differs from a production build.

Run the local WordPress environment:

```sh
npm run wp-env:start
```

Stop the local WordPress environment:

```sh
npm run wp-env:stop
```

Generate translation files:

```sh
composer run-script internationalize
```

### Quality checks

PHP checks are configured through `.phpcs.xml`, `.phpcs.tests.xml`, `.phpstan.neon`,
and the shared `a8csp/configs` package:

```sh
composer run-script lint:php
```

JavaScript, CSS, package metadata, and README markdown checks are defined in
`package.json`:

```sh
npm run lint:scripts
npm run lint:styles
npm run lint:pkg-json
npm run lint:readme-md
```

The Quality workflow runs `lint:scripts` and `lint:styles` on pull requests and
`trunk` pushes; `lint:pkg-json` and `lint:readme-md` run only locally.

### Tests

The suite has four PHPUnit tiers (Unit, Integration, Requirements, Multisite) plus a Playwright
end-to-end suite. See `tests/README.md` for how to run each suite, the wp-env ports involved, and
why the rig runs PHPUnit against plain `TestCase` instead of `WP_UnitTestCase`.

```sh
composer test:unit
composer test:integration
composer test:requirements
composer test:multisite
npm run test:e2e
```

### Maintenance notes

- Customize source files under `src/`, `includes/`, `models/`, `templates/`,
  `blocks/src/`, `assets/js/src/`, `assets/css/src/`, and `languages/`.
- Rebuild generated assets after changing block, editor, or `assets/css/src/` Sass
  sources. The tracked generated outputs live in `blocks/build/`, `assets/js/build/`,
  and `assets/css/build/` (compiled CSS, its RTL variant, and the CSS sourcemap); the
  build-integrity gate fails if they drift from a fresh build.
- Do not commit dependency directories such as `vendor/` or `node_modules/`.
