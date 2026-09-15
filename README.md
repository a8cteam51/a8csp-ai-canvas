# AI-Canvas

Let an AI agent build and edit pages and posts on a block-theme WordPress site through MCP.

Each page the agent writes is a normal WordPress page (or post) whose body is a single full-width **Custom HTML block**: markup, CSS and JS together, in `post_content`. The site owner can click text and images in wp-admin to tweak them later, WordPress records every update as a revision, and the agent can restore any revision. The plugin exposes the tools over an MCP endpoint, ships the authoring contract as the server's `instructions` so a client with no skill loaded still receives it, and renders the block on one of two page templates (with the theme header and footer, or blank).

## ⚠️ Read this first

**AI-Canvas deliberately trusts AI output.** Whatever the agent writes into the block is served to visitors unsanitized and runs same-origin: it can read the DOM, make credentialed REST requests, and act with the session of any logged-in viewer. This is cross-site scripting by design.

**Use it only on development sites or sites where you would happily hand the agent an editor account.** Do not run it on a site with real users or real customer data.

## Requirements

- WordPress **6.9+** (Abilities API in core), PHP **8.1+**
- A **block theme** (the templates use the theme's `header` and `footer` template parts)
- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) **≥ 0.6.1**
- HTTPS (Application Passwords require it, except on `WP_ENVIRONMENT_TYPE=local` sites such as WordPress Studio)

Optional: [Jamie's Visual HTML Editor](https://wordpress.org/plugins/jamies-visual-html-editor/) adds click-to-edit visual editing of the block in wp-admin. When it is active, AI-Canvas defers to it for the full-width wrapper.

## Setup

1. Install and activate `mcp-adapter`, then `ai-canvas`.
2. Create a dedicated user with the **Editor** role (or Administrator). Editors have everything the tools need on a single site: `edit_pages`, `publish_pages`, `upload_files`, and `unfiltered_html`. On multisite only super admins have `unfiltered_html`, and `DISALLOW_UNFILTERED_HTML` removes it for everyone; without it the tools refuse writes that contain scripts or styles rather than saving them stripped.
3. On that user's profile, create an **Application Password** (name it after the client, e.g. `claude-ai`). Copy it once, spaces included.
4. Build the header value: `Basic ` followed by `base64("username:application password")`.

The endpoint is `https://your-site.tld/wp-json/ai-canvas/mcp`. Without credentials it answers 401.

### claude.ai custom connector

1. **Customize → Connectors → Add custom connector** (Team/Enterprise owners: **Organization settings → Connectors → Add → Custom**).
2. MCP server URL: `https://your-site.tld/wp-json/ai-canvas/mcp`.
3. Authentication: **No sign-in**.
4. Under **Request headers**, add `authorization` with the value `Basic <base64 username:app-password>`, marked required.
5. Add, then enable the connector in a chat.

Request-header authentication is a claude.ai beta and is enabled per organization. If the **Request headers** section is missing from the dialog, your organization does not have it yet; there is no OAuth server in this plugin, so the connector cannot be added until it is.

### Claude Code

```bash
claude mcp add ai-canvas https://your-site.tld/wp-json/ai-canvas/mcp \
  -s user -t http -H "Authorization: Basic $(echo -n 'user:app-password' | base64)"
```

If the host strips the `Authorization` header before PHP sees it, ask the host to pass it through (Apache: `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`).

The Application Password is **not** scoped to these tools. It authenticates the whole REST API, so anything holding it is that user on that site. Treat it accordingly, and revoke it from the user's profile to cut the access off.

## MCP tools

Tool names are prefixed `ai-canvas-` on the wire (`ai-canvas-create-page`). Every tool requires `edit_pages` or `edit_posts`, and the endpoint enforces the same floor, so Subscribers cannot reach it.

| Tool | Does | Requires |
|---|---|---|
| `get-guidelines` | The full authoring contract (same text as the server `instructions`) | floor |
| `site-info` | Site, theme, connection user and capabilities, template availability, upload limit | floor |
| `find-pages` | Search pages or posts the user can edit, newest-modified first | floor, filtered by `edit_post` |
| `get-page` | One page/post with raw block content, validation warnings, revision count | `edit_post` |
| `create-page` | Create a page or post from one Custom HTML block; `template` framed (default for pages) / blank / theme; `status` publish (default) / draft / private | `edit_*` and `publish_*` for the type; `unfiltered_html` when the block has `<script>`/`<style>` |
| `update-page` | Replace the block and/or change title, status, slug, template, parent, excerpt; WordPress records a revision | `edit_post` (+ `publish_*` to publish, `unfiltered_html` as above) |
| `list-revisions` | Revisions newest first; the newest normally equals current content | `edit_post` |
| `restore-revision` | Restore a revision (content, title, excerpt); recorded as a new revision | `edit_post` (+ `unfiltered_html` if the revision has scripts/styles) |
| `trash-page` | Move to Trash | `delete_post` |
| `check-page` | Fetch the public URL and report: public, full-width wrapper, style/script present, script escaped, `&#038;` rewrite | `edit_post` |
| `list-media` / `upload-media` | Search the Media Library / add a file from URL or base64; results include image dimensions and generated sizes | `upload_files` |

### The content contract

`create-page` and `update-page` accept exactly one block:

```html
<!-- wp:html {"align":"full"} -->
<style data-wp-block-html="css"> /* every selector prefixed with the root class */ </style>
<script data-wp-block-html="js"> /* one IIFE, DOM-ready init, adds .is-js to the root */ </script>
<div class="canvas-spring-launch"> <!-- markup --> </div>
<!-- /wp:html -->
```

Anything else (two blocks, a paragraph block, no block delimiters) is rejected. The tools return non-fatal `warnings` for the common mistakes: missing `{"align":"full"}`, document markup (`<html>`, `<body>`), external stylesheets or scripts, data-URI images, a bare `<` inside the script, hidden states not gated on `.is-js`, no `canvas-` root element. The `data-wp-block-html` attributes match the CSS/JS tabs of the Custom HTML block in WordPress 7.1.

The full contract (scoping, `.is-js` gating, editor behaviour, performance rules, how to talk to the site owner) is `AI_Canvas_Guidelines::text()`. It is delivered three ways: as `instructions` in the MCP initialize response, from `get-guidelines`, and as one-line pointers in the create/update tool descriptions.

## How rendering works

- Two block templates are registered from PHP: **AI Canvas (with header and footer)** (`ai-canvas-framed`: header template part, `wp:post-content`, footer template part, no page title) and **AI Canvas (blank)** (`ai-canvas-blank`: the content only). Both appear in the editor's Template panel for pages and posts.
- `core/html` has no wrapper element, so its `align` attribute never reaches the front end. The plugin wraps aligned Custom HTML blocks in `<div class="wp-block-html alignfull">` (or `alignwide`) on render, and declares `align` support on the block so wp-admin saves keep the attribute. Both are skipped when Jamie's Visual HTML Editor is active.
- Block themes run the whole rendered template through `wptexturize()`, which turns a bare `<` inside a script into a fake tag and rewrites every `&` up to the next `>` as `&#038;`, breaking the script. On requests for a canvas page the plugin short-circuits `wptexturize` (`run_wptexturize`), and suspends it around `the_content` elsewhere. `check-page` still reports `script_ampersand_rewritten` because feeds and other plugins can run it.
- After every create, update, restore or trash the plugin bumps the page's Batcache version key, purges Pressable's edge cache when present, and fires `ai_canvas_after_write( $post_id, $change )`.

## Not in v1 (on purpose)

OAuth, output sanitization, classic themes, an embedded chat UI, settings screens.

## Changelog

### 1.0.0

- Pivot from file-based canvases to a single full-width Custom HTML block in `post_content`. Undo is WordPress revisions. The file jail, `.prev` rollback, editor lock and `ai-canvas/content` block are gone.
- New tool set: `get-guidelines`, `site-info`, `find-pages`, `get-page`, `create-page`, `update-page`, `list-revisions`, `restore-revision`, `trash-page`, `check-page`, plus the unchanged `list-media` / `upload-media`.
- The authoring contract ships in the plugin and is delivered as MCP `instructions`.
- Plugin-registered `ai-canvas-framed` / `ai-canvas-blank` templates, `alignfull` wrapper for `core/html`, `wptexturize` guard on canvas pages.
- Endpoint floor lowered from `edit_others_posts` to `edit_pages || edit_posts`; writes still require `unfiltered_html` when they carry scripts or styles.

### 0.2.0

- `rollback-file` tool; media tools return dimensions and generated sizes.

### 0.1.0

- Initial release: canvas templates, file jail, MCP endpoint, Media Library tools, cache purging, editor lockout card.
