# A8CSP AI Canvas

Lets Claude Design, or any MCP client, build pages on this site as a single Custom HTML block, and adds the page templates those pages render on.

The plugin does six things:

- registers two page templates, offered in the page editor's Template panel;
- exposes an MCP server whose tools create a page and then read, change, or trash it, but only a page flagged as open to them, and search the Media Library;
- gives editors and administrators a switch for that flag, on the Pages list and in the page editor; the tools set it on the pages they create;
- hands the connected model the rules for writing a page as one Custom HTML block of plain HTML, CSS, and JavaScript;
- adds an AI Canvas card to **Settings → Connectors** that turns an application password into the `Authorization` header an MCP client needs;
- lets the site owner refine such a page in the WordPress editor by clicking on it: change text, bold, italic, links, images and background images, without opening the code.

## Read this first

**This plugin publishes what the model writes.** The page content is HTML, CSS, and JavaScript, stored unsanitized and served to visitors from the site's own origin, so the script can do anything a script on the site can do. The write tools refuse to run for a user without the `unfiltered_html` capability, which is the capability WordPress reserves for exactly this. Use it on sites where you would let that user paste a script into a Custom HTML block by hand.

**The application password is not limited to these tools.** WordPress cannot scope one: it authenticates the whole REST API as its user. Create it for a dedicated user with the Editor role, not for an administrator.

## Requirements

- The WordPress and PHP versions the plugin header declares (`Requires at least`, `Requires PHP`).
- The [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) plugin, version 0.7 or newer. The plugin header requires it, so WordPress will not activate this plugin without it.
- A block theme, with `header` and `footer` template parts for the template that includes them. Classic themes are not supported: the two page templates are block templates, and WordPress does not render those under a classic theme.
- HTTPS, or a local environment: WordPress only accepts application passwords over one of the two.

## Installation

1. Install and activate **MCP Adapter** from **Plugins → Add New Plugin**.
1. Download `a8csp-ai-canvas.zip` from the latest release of this repository.
1. Upload it through **Plugins → Add New Plugin → Upload Plugin**, or unzip it into `/wp-content/plugins/`; it unpacks to an `a8csp-ai-canvas` folder.
1. Activate the plugin from the **Plugins** screen.

## Connecting Claude Design

1. As an administrator, open **Settings → Connectors** and choose **Set up** on the **AI Canvas** card.
1. Under **Connect as**, pick the user the connection will act as. The list holds the users who can edit pages; a dedicated user with the Editor role is the one to pick.
1. Follow the link under **Application password** to that user's profile, create an application password there, and paste it into the card.
1. Copy the **Server URL** and the **Authorization header**: the word `Basic`, then `username:application-password` base64-encoded. The header is computed in the browser; the password is not sent to the site or stored.
1. In Claude Design, add a custom connector with that server URL and that `Authorization` header value.

The Connectors screen is WordPress's own and needs the `manage_options` capability. The card is registered through the experimental API of that screen, so a later WordPress release can stop it from appearing; the MCP server does not depend on it. Without the card, build the header by hand:

```sh
printf 'Basic %s\n' "$(printf '%s' 'username:application-password' | base64)"
```

The server URL is `https://your-site.example/wp-json/a8csp-ai-canvas/mcp`.

## MCP tools

| Tool | Does |
| --- | --- |
| `get-instructions` | Returns the authoring rules below, for a client that does not read server instructions. |
| `create-page` | Creates a page from one Custom HTML block and flags it as open to AI Canvas. A draft unless `status` is `publish`. |
| `list-pages` | Lists the flagged pages. |
| `get-page` | Returns a flagged page with its content. |
| `update-page` | Changes a flagged page's content, title, status, or template. WordPress keeps the previous version as a revision. |
| `delete-page` | Moves a flagged page to the Trash. |
| `list-media` | Searches the Media Library. Images come back with their dimensions and generated smaller sizes. |

The tool names carry an `a8csp-ai-canvas-` prefix on the wire, as in `a8csp-ai-canvas-create-page`.

The flag is the `_a8csp_ai_canvas` post meta key. `get-page`, `update-page`, and `delete-page` refuse any page that does not carry it, so the connection cannot read or touch the rest of the site's pages through these tools. A trashed page is treated as gone.

Editors and administrators can switch it per page in two places: the **AI Canvas** column of the **Pages** list, and the **Let AI Canvas edit this page** switch in the page editor's settings sidebar. Switch it off to take a page back from the tools, or on to hand over a page they did not create. Either switch saves as soon as it is flipped, and changes nothing else about the page. Roles below Editor see neither switch and cannot change the flag. A page opened this way can be built from ordinary blocks, and `update-page` replaces its whole content with one Custom HTML block; the previous content stays in the page's revisions.

`list-media` is not limited by the flag: it searches the whole Media Library. No tool adds, changes or deletes a file.

Every tool needs the `edit_pages` capability, and the MCP endpoint itself refuses a user without it. On top of that, `create-page` and `update-page` need `unfiltered_html`, publishing needs `publish_pages`, each page needs the matching `edit_post` or `delete_post` capability, and `list-media` needs `upload_files`, as browsing the Media Library does in wp-admin. The tools are reachable only through this plugin's MCP server: they are not shown in the abilities REST API or on MCP Adapter's default server.

### Pictures

A page uses a picture the Media Library already has, or a placeholder. A model can only put a file into a tool call by writing its bytes out, which does not work for a photo, so the tools do not take files at all. For a picture the site does not have, the guide has the client:

- put the plugin's placeholder image, `assets/images/placeholder.svg`, in the picture's place, as an `<img>` or a CSS background, sized and cropped as the design has it;
- give the user one `.zip` of the pictures the placeholders stand for, the user's own files unchanged, and say which belongs where;
- ask before publishing a page that still has placeholders.

The site owner then [clicks each placeholder in the editor](#editing-a-page-by-clicking) and puts the real picture in, uploading it there if the library does not have it yet.

### The authoring instructions

The server sends the model a guide, as the MCP `instructions`, and again through `get-instructions`. It opens with one rule: design in Claude Design first, and write to the site only once the user has approved the design and asked for it to go there. `create-page` and `update-page` repeat that rule in their own descriptions, with a short summary of the content format and of where a picture the site does not have goes. The guide lives in [`src/Mcp/Instructions.php`](src/Mcp/Instructions.php). Its core is the block shape WordPress itself writes for a Custom HTML block, aligned to the full width:

```html
<!-- wp:html {"align":"full"} -->
<style data-wp-block-html="css">
.canvas-spring-launch { /* every selector starts with the root class */ }
</style>

<script data-wp-block-html="js">
/* one IIFE that waits for the DOM and queries from the root element */
</script>

<div class="canvas-spring-launch">
  <!-- markup -->
</div>
<!-- /wp:html -->
```

The tools enforce only that the content is exactly one Custom HTML block aligned to the full width. Everything else in the guide is guidance to the model.

## Page templates

Both templates are available to any page, from the Template panel of the page editor, and neither prints the page title.

- **AI Canvas: Header, Content, Footer** renders the theme's header, the page content, and the theme's footer.
- **AI Canvas: Content Only** renders the page content and nothing else.

Both are block templates, and the header and footer are the theme's `header` and `footer` template parts. The content area has no content-width column and no block gap, so a page's content runs the full width and sits flush against the header and footer. That suits one full-bleed block; a page built from ordinary blocks will want the theme's own templates.

On a page using either template, WordPress's `wptexturize()` is switched off. It otherwise rewrites `&&` in an inline script to `&#038;&#038;` whenever the script also holds a bare `<`, which breaks the script. The cost is that typed quotes and dashes on those pages are not curled.

### In the editor

The editor lays a page out in the theme's content column, which would show a page that fills the viewport squeezed into it. So while the plugin is active, the Custom HTML block can be aligned to the full width, the page tools only accept a block aligned that way, and the editor shows such a block across its full width. The block saves no wrapper element, so the alignment changes nothing for visitors.

The block's toolbar offers **Align → Full width** wherever the theme's layout offers alignments. On the two templates above it does not, because their content area has no content column to align against; a block that already carries the alignment is shown at full width there all the same.

## Editing a page by clicking

A page a model built is still the site owner's to change. Open it in the WordPress editor and click what you want to change:

- **Text.** Click a heading, a paragraph or a button and type. Select a stretch of text for a small toolbar with bold, italic and link; <kbd>Ctrl</kbd>/<kbd>⌘</kbd> + <kbd>B</kbd>, <kbd>I</kbd> and <kbd>K</kbd> do the same. <kbd>Enter</kbd> starts a new line, and in a button finishes the edit.
- **Links and buttons.** Click one and its toolbar shows where it goes. Choose it to type a new address, or search for a page of the site by its title, and to have the link open in a new tab. A link inside a sentence can also be removed.
- **Images.** Click one to replace it from the Media Library or to change its alternative text. The replacement keeps the size of the spot it fills. A grey box with a picture mark is a placeholder for a picture the site did not have when the page was made: click it to put the real one in.
- **Background images.** Click an empty part of a section that has one, and replace it.

Moving the pointer over the page outlines what a click would change, and names it. <kbd>Esc</kbd> lets go of what is selected. Everything is an ordinary edit to the page: **Save**, undo and redo work as they do for any other, and nothing reaches visitors until the page is saved.

This works on every Custom HTML block, not only on the pages a model made, and the block's **Edit code** is still there for anything a click cannot reach. Each change is written into the block's HTML where the change is, and the rest of the code is left exactly as it was, so a model that reads the page back finds what it wrote.

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

The architecture map and the component model live in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md);
the test workflow lives in [`tests/README.md`](tests/README.md).

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

2. **Bump the other two versions to the same string.** The release refuses to run unless the plugin header's `Version:` in `a8csp-ai-canvas.php`, `"version"` in `package.json`, and the newest `CHANGELOG.md` heading all state one version. The self-updater compares an installed copy against the header rather than against the tag, so the header bump belongs in the commit that gets tagged. Commit the three together and land them on trunk.

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
