# EXAMPLE_REPO_NAME

EXAMPLE_REPO_DESCRIPTION

## Requirements

This plugin is a WooCommerce extension. It needs the WordPress, PHP, and WooCommerce versions its plugin header declares (`Requires at least`, `Requires PHP`, `WC requires at least`); below the WooCommerce floor it stays off and shows an admin notice naming the version it needs.

## Installation

1. Download `EXAMPLE_REPO_SLUG.zip` from the latest release of this repository.
1. Upload it through **Plugins → Add New Plugin → Upload Plugin**, or unzip it into `/wp-content/plugins/`; it unpacks to an `EXAMPLE_REPO_SLUG` folder.
1. Activate the plugin from the **Plugins** screen.

With WooCommerce active at the required version, the plugin's settings appear in two places: a field on the **Settings → General** page, and a section of the **Advanced** tab on the WooCommerce **Settings** page.

## Updates

The plugin updates itself from this repository's GitHub releases through its `Update URI` header, so WordPress offers a new release like any other plugin update — as long as the repository is public, because the check calls the GitHub API without authentication. OpsOasis creates repositories private, so a plugin generated through it does not update itself. An installed prerelease (a version containing `-`) follows every release; a stable installation follows stable releases only.

## Development

Install dependencies, build the assets, and start the local environment:

```sh
composer install
npm install
npm run build
npm run wp-env:start
```

The plugin is available at the wp-env port declared in `.wp-env.json`; `tests/README.md` documents the dedicated test environment. wp-env publishes the site on all network interfaces with fixed development credentials — treat the dev site as visible to your local network, not just localhost.

The architecture map, the component model, and the reshaping recipes (including watering the
plugin down to plain WordPress) live in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md); the test
workflow lives in [`tests/README.md`](tests/README.md).

`composer packages-update` and `npm run packages-update` update every dependency within the range its manifest declares. `npm run packages-update:wp` moves the `@wordpress/*` packages, across major versions, to the npm dist-tag named in the script; keep that tag at the plugin's WordPress floor.

### Releasing

Remove the examples this plugin does not need before its first release. They ship until removed, and the integrations in `src/Integrations/` change a live store: on a site running WooCommerce Subscriptions or WooPayments, they append an example note to every subscription price and add an example key to the metadata WooPayments sends with each payment. [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) maps each example.

Every pull request that changes behavior carries a changelog fragment:

```sh
composer changelog:add
```

The command asks for a significance (`patch`, `minor`, `major`), a type, and the entry text, then writes one file under `changelog/`; `-s`, `-t` and `-e` supply the same answers non-interactively. `composer changelog:validate` only checks the fragments that exist and passes on an empty directory, so a forgotten fragment surfaces as a missing release note rather than as a red check.

Releases are cut from trunk, in four steps.

1. **Materialize the changelog.** `composer changelog:write` derives the next version from the newest `CHANGELOG.md` entry and the pending fragments' significance, writes that section, and deletes the fragments it consumed. There is nothing to derive from while the changelog is empty, so the first release names its whole version with `--use-version`, a first prerelease included. After that, the derived version is not always the next one on the current line: from a prerelease entry it moves to the next line when the pending significance does not fit that line, and a `--prerelease` suffix that sorts before the current one moves it too. Preview it with `vendor/bin/changelogger version next`, which takes the same `--prerelease`, and pin the version with `--use-version` whenever the preview is not the release you mean:

   ```sh
   composer changelog:write -- --use-version=1.0.0-beta.1
   vendor/bin/changelogger version next --prerelease=beta.2
   composer changelog:write -- --prerelease=beta.2
   composer changelog:write -- --use-version=1.0.0
   ```

2. **Bump the other two versions to the same string.** The release refuses to run unless the plugin header's `Version:` in `EXAMPLE_REPO_SLUG.php`, `"version"` in `package.json`, and the newest `CHANGELOG.md` heading all state one version. The self-updater compares an installed copy against the header rather than against the tag, so the header bump belongs in the commit that gets tagged. Commit the three together and land them on trunk.

3. **Let trunk go green, then rehearse.** The release reuses the trunk-push Quality and Tests runs from the exact commit it tags, so tag only once those have finished. A later push to trunk cancels them if they are still running, and a cancelled run fails provenance as a red one does, so confirm this commit's own runs concluded successfully and re-run any that were cancelled. Those runs include legs outside the supported matrix, WordPress nightly and the next PHP, and every PHPUnit leg runs its tests in random order; a red leg that ships no defect still blocks provenance until a re-run passes. With them green, run the **Release** workflow from the Actions tab leaving **Create the GitHub release** off: that exercises the version check, the provenance check, the build and the smoke install without creating anything.

4. **Tag the green commit and publish the tag.**

   ```sh
   git tag -s "v1.0.0" -m "v1.0.0"
   git push origin "v1.0.0"
   ```

   Pushing a `v*` tag runs the release, and the publish step passes `--verify-tag`, so the tag has to reach the remote before the release can be created.

The release history is [`CHANGELOG.md`](CHANGELOG.md).

## Publishing on wordpress.org

This plugin releases as a GitHub zip. Publishing it in the wordpress.org plugin directory is a per-plugin decision, and it takes at least:

- a `readme.txt` in the directory's readme format;
- the slug wordpress.org assigns from the plugin name, which can differ from this repository's name, with the `Text Domain` changed to match it so language packs apply;
- removing the GitHub self-updater (the `Update URI` header and its `update_plugins_github.com` and `plugins_api` filters), because directory plugins may only update through wordpress.org;
- a release step that deploys each tagged release to the plugin's wordpress.org SVN repository.
