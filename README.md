# A8CSP AI Canvas WebMCP

Lets the AI agent in your browser build a page in the page editor as a single Custom HTML block, through WebMCP.

The plugin does three things:

- registers two page templates, offered in the page editor's Template panel;
- in the editor of a page, registers five [WebMCP](https://github.com/webmachinelearning/webmcp) tools, which let an agent in the browser read the authoring rules and the page, write it as one full-width Custom HTML block of plain HTML, CSS, and JavaScript, choose its template, and find images in the Media Library;
- lets the user refine the page in the editor by clicking on it: change text, bold, italic, links, images and background images, without opening the code.

## Read this first

**WebMCP is experimental.** It is a W3C Community Group draft, Chrome and Edge offer it behind a flag or in an origin trial, and its API is still changing. In a browser without it, the templates and the editing by clicking still work, and the tools are not there.

**The agent writes as the signed-in user.** The page content is HTML, CSS, and JavaScript, stored unsanitized and served to visitors from the site's own origin, so the script can do anything a script on the site can do. The tools refuse to write for a user without the `unfiltered_html` capability, which is the capability WordPress reserves for exactly this. Nothing reaches visitors until the user publishes the page, so read what the agent wrote before publishing it.

## Requirements

- The WordPress and PHP versions the plugin header declares (`Requires at least`, `Requires PHP`).
- A block theme, with `header` and `footer` template parts for the template that includes them. Classic themes are not supported: the two page templates are block templates, and WordPress does not render those under a classic theme.
- For the tools, a browser with WebMCP and an agent that calls WebMCP tools. For local development in Chrome, turn on `chrome://flags/#enable-webmcp-testing` and relaunch.

## Installation

1. Download `a8csp-ai-canvas-webmcp.zip` from the latest release of this repository.
1. Upload it through **Plugins → Add New Plugin → Upload Plugin**, or unzip it into `/wp-content/plugins/`; it unpacks to an `a8csp-ai-canvas-webmcp` folder.
1. Activate the plugin from the **Plugins** screen.

## Building a page with an agent

1. Add a new page.
1. Ask the agent in your browser to build it. The agent reads the rules for the content and the page, then writes the page and chooses its template.
1. Each change appears in the editor at once, and undo takes it back. The editor does not run the page's script: check its behaviour in **Preview**.
1. Publish the page when it is ready. The agent cannot.

The tools write only to a page that is empty or is already one Custom HTML block, so they never overwrite a page built from other blocks. When the user changes the page between the agent reading it and writing it, the write is refused and the agent reads the page again, so the user's change is not lost.

## WebMCP tools

| Tool | Does |
| --- | --- |
| `ai-canvas-get-instructions` | Returns the authoring rules: the content format, the templates, and how to work with the user. |
| `ai-canvas-get-page` | Returns the page's title, template, CSS, JavaScript and markup, a `revision` for the next write, and whether the agent can write to it. Marked as returning untrusted content. |
| `ai-canvas-set-page-content` | Replaces the page's content with one full-width Custom HTML block built from the CSS, JavaScript and markup the agent sends, and can set the title. Refuses a stale `revision`, and refuses to write until the agent has read the authoring rules. |
| `ai-canvas-set-template` | Chooses `header-footer` or `content-only`. |
| `ai-canvas-list-media` | Searches the Media Library for images, returned with their dimensions and generated smaller sizes. |

WebMCP has no instructions that apply to all of a page's tools, so `ai-canvas-set-page-content` refuses to write until the agent has called `ai-canvas-get-instructions` in the tab. The rules have a tool of their own because the page's title and content are written by the site's users, so `ai-canvas-get-page` is marked as returning untrusted content, and the rules are not. They live in `assets/js/src/webmcp/instructions.js`.

The tools are registered as one set: if any of them fails to register, the rest are unregistered, so an agent never finds a tool that sends it to one that is missing. In a browser without WebMCP, the editor notes in the console that the tools are not registered.

### With Claude in Chrome

As of November 2026, the Claude in Chrome extension does not list a page's WebMCP tools as tools of its own, but it can call them through JavaScript in the page. Turn on the WebMCP flag, open the page in the editor, and start the session with:

```text
Edit this page only through its WebMCP tools, using JavaScript in the page:
  const tools = await document.modelContext.getTools();
  const call = async (name, input = {}) =>
    JSON.parse(await document.modelContext.executeTool(tools.find(t => t.name === name), input));
Call ai-canvas-get-instructions first and read all of it, in 1,500-character slices.
Call ai-canvas-get-page before every change. Don't change the editor's data any other way.
```

This route has limits an agent that lists WebMCP tools itself does not:

- Long results can come back cut short, which is why the prompt has the agent read the rules in slices.
- The extension can withhold a result it takes for cookie or query-string data, so `ai-canvas-get-page` may come back blocked when the page links to a URL with a query string.
- The agent does not see the tools' annotations, so the page's content reaches it without the untrusted-content marking.
- The tools' checks hold only while the agent goes through the tools. The same JavaScript can change the editor's data directly, skipping the `revision` check and the rules, so read what the agent wrote before publishing, as with any agent.

## Page templates

- **AI Canvas: Header, Content, Footer** renders the site's own header and footer around the content.
- **AI Canvas: Content Only** renders the content alone, for a standalone landing page.

Neither prints the page title, and both give the content the full width of the viewport.

## Editing a page by clicking

A page an agent built is still the site owner's to change. Open it in the WordPress editor and click what you want to change:

- **Text.** Click a heading, a paragraph or a button and type. Select a stretch of text for a small toolbar with bold, italic and link; **Ctrl**/**⌘** + **B**, **I** and **K** do the same. **Enter** starts a new line, and in a button finishes the edit.
- **Links and buttons.** Click one and its toolbar shows where it goes. Choose it to type a new address, or search for a page of the site by its title, and to have the link open in a new tab. A link inside a sentence can also be removed.
- **Images.** Click one to replace it from the Media Library or to change its alternative text. The replacement keeps the size of the spot it fills.
- **Background images.** Click an empty part of a section that has one, and replace it.

Moving the pointer over the page outlines what a click would change, and names it. **Esc** lets go of what is selected. Everything is an ordinary edit to the page: **Save**, undo and redo work as they do for any other, and nothing reaches visitors until the page is saved.

This works on every Custom HTML block, not only on the pages an agent made, and the block's **Edit code** is still there for anything a click cannot reach. Each change is written into the block's HTML where the change is, and the rest of the code is left exactly as it was, so an agent that reads the page back finds what it wrote.

Some things it does not do. It changes what is on the page; it does not add, remove or move sections. The page's script does not run in the editor, as in core's own preview, so anything only the script reveals is edited from the code. A block whose markup a browser has to repair, such as mismatched tags, cannot be edited by clicking, and says so on the first click. And for a user without the `unfiltered_html` capability it is off on blocks that hold CSS or JavaScript, because that user's save would strip them.

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

2. **Bump the other two versions to the same string.** The release refuses to run unless the plugin header's `Version:` in `a8csp-ai-canvas-webmcp.php`, `"version"` in `package.json`, and the newest `CHANGELOG.md` heading all state one version. The self-updater compares an installed copy against the header rather than against the tag, so the header bump belongs in the commit that gets tagged. Commit the three together and land them on trunk.

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
