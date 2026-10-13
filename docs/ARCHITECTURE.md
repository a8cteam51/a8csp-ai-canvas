# Architecture

This is the map of the plugin: what each file owns, how the pieces boot, and the recipes for
reshaping them.

## The component model

A plugin is a list of components; a component is a class with a static `should_load()` gate, an
`initialize()` readiness phase, and a `register_hooks()` attachment phase; the boot is a few
foreach loops you can read — gate and construct, initialize all, then register all hooks, so
every surviving component is initialized before any hook can fire.

## The map

- `a8csp-ai-canvas.php` defines the plugin header and constants, requires
  `functions-bootstrap.php`, and wires the self-updater, the requirements gate, and the plugin
  boot. Its `Requires Plugins` header names MCP Adapter, so WordPress refuses to activate the
  plugin without it.
- `functions-bootstrap.php` provides the GitHub release updater, plugin metadata,
  version-compatibility checks, the requirements gate, and its admin-notice reporter; both root
  bootstrap files stay parsable below the plugin's PHP floor, and CI lints them against the older
  PHP versions and runs the entry file there to prove the requirements gate stops at its notice.
- `functions.php` provides the construction-only plugin accessor (booting stays tied to the
  `plugins_loaded` attachment in the entry file) and loads the PHP helper files under `includes/`.
- `src/` follows one folder per feature, each owning a `Component` that composes it; the `src/`
  root holds only the bootstrapping mechanism. `src/ComponentInterface.php` is the one contract,
  and `src/Plugin.php` is the one file to edit when adding components to `COMPONENTS`; they boot
  in registration order. `src/ComponentCollection.php` is the shared gated collection the
  composition root delegates its gate-construct and phase loops to — has-a, not is-a: the
  collection does not implement the contract. `src/AbstractComponent.php` is the optional
  defaults-only base (open gate, no-op readiness) for components that need neither.
- `src/Templates/` provides the two page templates, keyed by the short names the MCP tools
  accept (`Component::SLUGS`), as block templates. It also owns the two filters a page on those
  templates needs: one returns block-template queries as a list, because core reads
  plugin-registered templates by index and warns in the editor otherwise, and one switches
  `wptexturize()` off on those pages, because it corrupts inline scripts.
- `src/HtmlBlock/` is what the editor adds to core's Custom HTML block. In PHP it is small: it
  adds a full-width alignment to the block type's supports, which registers the `align`
  attribute the MCP page tools require of the block they write, and it enqueues the block's
  editor script and stylesheet. The alignment exists for the editor: the block saves no wrapper
  element, so nothing changes on the front end. The editor only offers and honours an alignment
  inside a constrained layout, and the two page templates use the flow layout, so the script
  (`assets/js/src/html-block.js`) gives a full-width Custom HTML block the aligned wrapper
  itself. The rest of the script is the visual editor, described below.
- `src/Mcp/` is the MCP feature, gated on MCP Adapter being loaded. `Component.php` registers
  the tools as abilities and creates the plugin's MCP server at `/wp-json/a8csp-ai-canvas/mcp`,
  passing the authoring guide as the server description, which the adapter serves as the MCP
  `instructions`. `Pages.php` is the plain collaborator holding the page operations; every
  operation on an existing page goes through its `resolve()`, which is where the flag that
  opens a page to the tools (`PageAccess\Flag`) is enforced.
  `Media.php` is the collaborator holding the Media Library search; no tool adds a file, so a
  page's pictures are files the site owner put in the library. `Instructions.php` is the
  authoring guide, into which `Component.php` passes the link to the site's upload screen.
- `src/PageAccess/` is an editor's control over that flag. `Flag.php` is the flag itself: the
  meta key, and the only code that reads or writes it. `Component.php` adds the AI Canvas column
  to the Pages list, enqueues the two switches (`assets/js/src/pages-list.js` for the column,
  `assets/js/src/page-editor.js` for the page editor's sidebar), and registers the REST route
  both read and save through (`assets/js/src/lib/use-page-access.js`). The flag is not
  registered with core's page route on purpose: that route re-saves the whole page, and the
  editor re-sends every meta key on each save. The column, both switches and the route need
  `edit_others_pages`, the capability that separates an Editor from the roles below.
- `src/Connector/` adds the AI Canvas card to core's Settings → Connectors screen. In PHP it
  registers a connector with core (`wp_connectors_init`) and, when that screen loads, enqueues
  the card's script with the server URL and the users a client can connect as. The script
  (`assets/js/src/connector.js`) is the card's React UI. It persists nothing: its one tool,
  the `Authorization` header generator, runs in the browser. Core's screen is built from
  script modules, and the registration API is an `__experimental` export of one of them, so
  the script imports `@wordpress/connectors` at runtime instead of at build time.
- `includes/` contains automatically loaded procedural helpers, including the asset-meta reader;
  PHP files dropped there load automatically inside WordPress, while underscore-prefixed files
  are skipped.
- `languages/` holds the plugin's translations; `composer i18n:makepot` generates the POT from the
  plugin's strings, and every release regenerates it.
- `models/` is the extension point for namespace-less classmapped classes that are part of a
  public contract.
- `footprint.php` is the dependency-free manifest of the complete persisted footprint — every
  option, user-meta and post-meta key grouped by owning component — and `uninstall.php` requires
  it and deletes those keys during WordPress's cold uninstall bootstrap; add an entry with each
  corresponding write, and cross-reference persisted keys with `@see footprint.php` in the
  owning class docblock. Uninstalling removes the page flag, not the pages.
- `assets/js/src/` holds the script sources and `assets/js/build/` the tracked build output;
  `npm run build` is the single build contract. `assets/css/src/` holds the one stylesheet, the
  visual editor's, which the script that uses it imports, so it is built beside that script.
  `assets/images/` holds the connector's logo.
- `tests/` contains the automated test suite, PHP and JavaScript; see `tests/README.md` for the
  local workflow.
- `.github/workflows/` contains the CI workflows.

## The visual editor

The visual editor lets a page built as one Custom HTML block be refined by pointing and clicking:
text is typed over where it stands, and an image, a link or a background image is changed from a
toolbar beside it. It lives in `assets/js/src/visual-editor/` and runs only in the block editor.
Nothing about it reaches the front end, and what it saves is still one Custom HTML block.

It does not replace the block's edit component. `index.js` wraps it (`editor.BlockEdit`), so core's
code editor and its "Edit code" button stay, and `editor.js` adds a `Surface` to the element core
renders the block into. Three decisions shape the rest.

**The source is edited, not regenerated.** The obvious way to save an edit is to read the page's
DOM back as HTML. That rewrites the whole block in the browser's own style on the first keystroke,
and saves whatever the page has picked up since it loaded. Instead, `tokenizer.js` finds every tag
of the block's HTML with its offsets, and `source-document.js` rewrites one range or one start
tag of that string. A changed word changes that word in the source, and the rest stays as the
model wrote it, byte for byte.

**Nothing is written on trust.** The tokenizer is not a parser, and markup a browser repairs has
no simple mapping back to its source. So `dom-map.js` checks before it writes. On load it parses
the HTML with every start tag numbered, and takes the block only if the numbers come back once
each and in order. Before the first write to an element it parses what it would write and
compares that with what the source holds. An element that does not hold up is written through the
nearest ancestor that does, and a block that cannot be mapped is left to core, with a notice on
the first click. `dom-map.js` also never copies an existing tag from the page: an element that
came from the source is written with the start tag the source has, so an attribute a browser
extension added to the page, or a `details` opened to reach its text, is not saved.

**The block editor owns the document.** Each change goes to the block editor as the block's new
inner content, at once. Saving, the dirty state, undo and redo are the editor's, and an undo or a
change made in the code editor comes back to the `Surface` as new HTML to show. The `Surface`
takes over rendering from core for one reason: core replaces the block's DOM on every change, and
a caret does not survive that.

The other modules are the pieces a `Surface` puts together: `targets.js` decides what a click
edits, `formatting.js` does bold, italic and links on the text being edited, `background.js`
finds the `url()` behind a background image, `media.js` picks the file of a Media Library image
to use, `overlay.js` draws the boxes over the content, and `toolbar.js` is the toolbar, built from
the block editor's own components.

Scripts in the block do not run in the editor, and inline event handlers are renamed for as long
as the block is shown there, as core's own rendering strips them. The visual editor is off for a
block with other blocks inside it, for a locked or previewed block, and for a user without
`unfiltered_html` when the block has a style or a script: that user's save would strip them, and
should not be invited.

Whatever throws in the visual editor is caught where it meets the block editor's rendering, and
the block falls back to core's.

## Multisite

The plugin is expected to support multisite networks and to be tested on one when the site runs
one. Multisite is a design consideration while building, not a porting step at the end — when
adding a component, decide its scope deliberately:

- Options and post meta are per-site; user meta is network-global. `uninstall.php` models the
  consequence: its options and post-meta sweep visits every site of a network in bounded
  batches, while its user-meta pass runs once.
- Only network administrators hold `unfiltered_html` on a network, so only they can use the
  write tools there.
- The requirements gate reports through `all_admin_notices`, which fires on site and network
  admin screens alike, so a failed network activation is explained where it happened.
- The plugin registers no activation or deactivation hooks. A plugin that adds them must
  handle the `$network_wide` activation flag and provision sites created after network
  activation (`wp_initialize_site`).
- The component `should_load()` gates run on every request, so per-site environmental
  differences — such as MCP Adapter being active on only some sites — resolve correctly site
  by site.
- The multisite wp-env fixture (`.wp-env.multisite.json`) converts itself into a network on
  start, and `composer test:multisite` proves the uninstall sweep against it.

## Growing the plugin

A new feature is a new folder under `src/` with a `Component`, added to `COMPONENTS` in
`src/Plugin.php`. A component that depends on another plugin gates on it in `should_load()`, as
`src/Mcp/Component.php` does; a second class in a feature is a plain collaborator its component
constructs in `initialize()`, as `Mcp\Pages` is.

A new MCP tool is an ability registered in `Mcp\Component::register_abilities()` and named in
its `ABILITIES` list. A tool that acts on an existing page resolves it through
`Pages::resolve()`, so the flag check cannot be skipped.

Anything the plugin starts to persist is listed in `footprint.php` in the same change that
introduces the write.

## Version tags on transplant

Every PHP file here carries `@since` and `@version` in its docblocks, because this plugin ships
from tagged releases and a reader can place any member against a published version. A component
with no release channel — a continuously deployed mu-plugin, a single site's own theme — has
nothing for the pair to name, so it omits them repo-wide rather than running two conventions side
by side. Moving a file into such a repository drops the pair; moving one back out restores it.
