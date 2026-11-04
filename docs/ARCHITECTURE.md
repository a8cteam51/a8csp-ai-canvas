# Architecture

This is the map of the plugin: what each file owns, how the pieces boot, and how to grow it.

## The component model

A plugin is a list of components; a component is a class with a static `should_load()` gate, an
`initialize()` readiness phase, and a `register_hooks()` attachment phase; the boot is a few
foreach loops you can read — gate and construct, initialize all, then register all hooks, so
every surviving component is initialized before any hook can fire.

## The map

- `a8csp-ai-canvas.php` defines the plugin header and constants, requires
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
  in registration order. `src/ComponentCollection.php` is the shared gated collection the
  composition root delegates its gate-construct and phase loops to — has-a, not is-a: the
  collection does not implement the contract. `src/AbstractComponent.php` is the optional
  defaults-only base (open gate, no-op readiness) for components that need neither.
- `src/Templates/` provides the two page templates, keyed by the short names the
  `ai-canvas-set-template` tool accepts (`Component::SLUGS`), as block templates. It also owns the
  two filters a page on those templates needs: one returns block-template queries as a list,
  because core reads plugin-registered templates by index and warns in the editor otherwise, and
  one switches `wptexturize()` off on those pages, because it corrupts inline scripts.
- `src/HtmlBlock/` is what the editor adds to core's Custom HTML block. In PHP it is small: it
  adds a full-width alignment to the block type's supports, which registers the `align`
  attribute the tools give the block they write, and it enqueues the block's editor script and
  stylesheet. The alignment exists for the editor: the block saves no wrapper element, so nothing
  changes on the front end. The editor only offers and honours an alignment inside a constrained
  layout, and the two page templates use the flow layout, so the script
  (`assets/js/src/html-block.js`) gives a full-width Custom HTML block the aligned wrapper
  itself. The rest of the script is the visual editor, described below.
- `src/WebMcp/` enqueues the tools script (`assets/js/src/webmcp.js`) in the block editor of a
  page, and nowhere else, handing it `Templates\Component::SLUGS`. The script registers the tools
  with the browser's WebMCP API, on `document.modelContext` or, in earlier Chrome builds,
  `navigator.modelContext`, and in a browser without it registers nothing and notes so in the
  console. The tools are described below.
- `includes/` contains automatically loaded procedural helpers, including the asset-meta reader;
  PHP files dropped there load automatically inside WordPress, while underscore-prefixed files
  are skipped.
- `languages/` holds the plugin's translations; `composer i18n:makepot` generates the POT from the
  plugin's strings, and every release regenerates it.
- `models/` is the extension point for namespace-less classmapped classes that are part of a
  public contract.
- `footprint.php` is the dependency-free manifest of the complete persisted footprint — every
  option and user-meta key grouped by owning component — and `uninstall.php` requires it and
  deletes those keys during WordPress's cold uninstall bootstrap. The plugin persists nothing of
  its own today, so the manifest is empty; add an entry with each corresponding write, and
  cross-reference persisted keys with `@see footprint.php` in the owning class docblock.
- `assets/js/src/` holds the script sources and `assets/js/build/` the tracked build output;
  `npm run build` is the single build contract. `assets/css/src/` holds the one stylesheet, the
  visual editor's, which the script that uses it imports, so it is built beside that script.
- `tests/` contains the automated test suite, PHP and JavaScript; see `tests/README.md` for the
  local workflow.
- `.github/workflows/` contains the CI workflows.

## The tools

The tools live in `assets/js/src/webmcp/`. They work on the page open in the editor and nothing
else, as the signed-in user, through the editor's own data stores: a change is an ordinary edit,
which undo takes back and which reaches visitors only when the user publishes the page. There is
no save or publish tool.

`register.js` registers the tools as one set. Every registration takes the same abort signal, and
when any of them fails, aborting it unregisters the rest, so an agent never sees a tool without
the tools it sends the agent to.

`tools.js` defines the five tools. They read and write the editor's copy of the page
(`getEditorBlocks()`, `resetEditorBlocks()`), not the blocks on screen, which also include the
template when the editor shows it. A page is `empty` when it holds nothing but the editor's
unchanged starting paragraph, `canvas` when it is one Custom HTML block, and `other` otherwise;
the write refuses an `other` page, so a page built from other blocks is never overwritten.

`page-content.js` joins the CSS, JavaScript and markup an agent sends into the block's HTML,
splits that HTML back up for a read, and checks what was sent for what would break the page: a
block comment, an end tag that closes the style or script element early, a whole document, and
markup that is not one root element with a class. It also computes the `revision`, a hash of the
block's HTML: `ai-canvas-get-page` returns it and `ai-canvas-set-page-content` must send it back,
so a write that would overwrite an edit the user made in between is refused.

`instructions.js` is the authoring guide, which `ai-canvas-get-instructions` returns. WebMCP has
no instructions that apply to all of a page's tools, so `ai-canvas-set-page-content` refuses to
write until the agent has called it in the tab, and the `revision` makes reading the page the one
way to a write. The guide is not part of `ai-canvas-get-page` because that tool returns the page's
title and content, which the site's users write, and carries `untrustedContentHint`; the guide is
the plugin's own and should not be read as untrusted.

The tools answer a refusal with an `error` result rather than by throwing, because WebMCP hands an
agent nothing of a thrown error but the fact that the tool failed; Chrome answers the caller with
a generic `UnknownError`. That includes a failed Media Library request. Chrome does not hold a call
to its input schema's `required` list, so each tool checks its input itself, and a write checks
what the agent sent before the revision, so a call missing a part is told so rather than that the
page changed. Chrome passes the input as a JSON string up to version 154, so each tool accepts
either form. Everything the tools say is
addressed to the agent, so none of it is translated.

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
agent or the person editing the code wrote it, byte for byte.

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
finds the `url()` behind a background image and edits the declarations around it, `media.js` picks
the file of a Media Library image to use, `overlay.js` draws the boxes over the content, and
`toolbar.js` is the toolbar, built from the block editor's own components.

**Background images are found where they are painted.** A section often puts its image on a layer
under its content, a sibling of the content rather than an ancestor, or on its `::before` or
`::after`. So `targets.js` looks at what `elementsFromPoint()` says is under the pointer, and at
each element's pseudo-elements, as well as up the ancestors, and adds the layers beside the
ancestors that the pointer passes through (`pointer-events: none`). A background is offered only
when `background.js` finds its `url()` in the block: in the element's style attribute, or in a
style element, preferring the last rule whose selector names the element or that pseudo-element.
Text, an image or a link in front of a background has a **Background** button that selects it, so a
background is never only reachable through the gaps in its content; for text being typed in, the
button's toolbar shows until the typing starts, and again once the pointer moves.

A background's three changes each rewrite as little as possible. Replace swaps the one `url()`.
Remove writes `none` in its place, which is a whole layer of its own, so the declaration stays
valid however many layers it has; an image inside another function, such as `image-set()`, is
not offered. The focal point is a `background-position`, set in the rule or attribute the image is
written in when that is the element's own, or else in the element's style attribute, which comes
after every rule. Each place is tried on the page first and kept only if the image moves there,
so a more specific rule that would win is not written under; a pseudo-element, having no style
attribute, has only its own rule.

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

- Options are per-site; user meta is network-global. `uninstall.php` models the consequence:
  its options sweep visits every site of a network in bounded batches, while its user-meta pass
  runs once.
- The requirements gate reports through `all_admin_notices`, which fires on site and network
  admin screens alike, so a failed network activation is explained where it happened.
- The plugin registers no activation or deactivation hooks. A plugin that adds them must
  handle the `$network_wide` activation flag and provision sites created after network
  activation (`wp_initialize_site`).
- The component `should_load()` gates run on every request, so per-site environmental
  differences — such as a companion plugin being active on only some sites — resolve correctly
  site by site.
- The multisite wp-env fixture (`.wp-env.multisite.json`) converts itself into a network on
  start, and `composer test:multisite` proves the uninstall sweep against it.

## Growing the plugin

A new feature is a new folder under `src/` with a `Component`, added to `COMPONENTS` in
`src/Plugin.php`. A component that depends on another plugin gates on it in `should_load()`; a
second class in a feature is a plain collaborator its component constructs in `initialize()`.

A new tool is an entry in the `TOOLS` list in `assets/js/src/webmcp/tools.js`, named with the
`ai-canvas-` prefix so it stays apart from other plugins' tools on the same page. A tool that
writes to the page goes through `readPage()` and refuses an `other` page, as
`ai-canvas-set-page-content` does.

Anything the plugin starts to persist is listed in `footprint.php` in the same change that
introduces the write.

## Version tags on transplant

Every PHP file here carries `@since` and `@version` in its docblocks, because this plugin ships
from tagged releases and a reader can place any member against a published version. A component
with no release channel — a continuously deployed mu-plugin, a single site's own theme — has
nothing for the pair to name, so it omits them repo-wide rather than running two conventions side
by side. Moving a file into such a repository drops the pair; moving one back out restores it.
