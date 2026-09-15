<?php
/**
 * The authoring guide: the single source of the rules an agent needs to write
 * page content for this site. Served as the MCP server's `instructions`, by
 * the get-guidelines tool, and as one-line pointers in tool descriptions.
 *
 * @package Ai_Canvas
 */

defined( 'ABSPATH' ) || exit;

class AI_Canvas_Guidelines {

	const VERSION = '1';

	/**
	 * One sentence appended to the create/update tool descriptions.
	 */
	public static function pointer(): string {
		return 'The content must be exactly one <!-- wp:html {"align":"full"} --> block containing a <style data-wp-block-html="css">, a <script data-wp-block-html="js">, and one root <div> with a distinctive class; call get-guidelines once per session before the first write for the full contract.';
	}

	/**
	 * The block every page or post body is made of.
	 */
	public static function block_template(): string {
		return <<<'HTML'
<!-- wp:html {"align":"full"} -->
<style data-wp-block-html="css">
/* all CSS, every selector prefixed with the root class */
/* JS-driven states (hidden until revealed, inactive tab panels) only under .is-js */
</style>

<script data-wp-block-html="js">
/* all JS: one IIFE that waits for the DOM, then queries from the root element */
(function () {
  function init() {
    var root = document.querySelector('.canvas-spring-launch');
    if (!root) return;
    root.classList.add('is-js');
    /* behaviour here */
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
</script>

<div class="canvas-spring-launch">
  <!-- markup -->
</div>
<!-- /wp:html -->
HTML;
	}

	public static function text(): string {
		$block = self::block_template();

		return <<<MD
# AI-Canvas: building pages and posts on this WordPress site

You build and edit pages and posts on a WordPress site through these tools. Each page or post body is one full-width Custom HTML block (markup, CSS and JS together). WordPress stores it as a normal page, the site owner can click text and images in wp-admin to tweak them later, and every update is recorded as a revision the owner can see and you can restore.

Assume the person you are talking to is non-technical unless they show otherwise. That changes how you talk, not what you build.

## Preflight

Call `site-info` once per session before the first write. It reports the connection user's capabilities and whether the site can render scripts and styles. If `can_write_scripts_and_styles` is false, say so plainly ("the connection to your site isn't allowed to publish interactive pages; that's a WordPress setting") and do not attempt a workaround. If a tool returns an error, translate it before showing it: say what happened and what happens next, in the user's terms.

## The block

Every page or post body is exactly this shape:

```html
{$block}
```

- `{"align":"full"}` makes the block span the full screen width. The plugin wraps the block in the theme's full-width container on the front end.
- **It is a fragment, not a document.** No `<!DOCTYPE>`, `<html>`, `<head>`, `<body>`, `<title>`, meta tags, or external `<link>`/`<script>` references. The theme supplies all of that.
- **One root element with a distinctive class**, and every CSS selector prefixed with it. Never style `body`, `html`, `:root`, or bare element selectors; that restyles the theme's header and footer and reads as a broken site. If the design fights the theme's content width, work inside the root instead of overriding theme layout classes.
- **JS runs same-origin and unsandboxed.** Keep it self-contained: DOM behaviour, animation, small interactions. Never call authenticated site endpoints, embed third-party scripts, or send data anywhere. Wrap it in an IIFE and query from the root element so two blocks on one site cannot collide.
- **The script runs before the markup exists.** It sits above the root element in the block and WordPress prints it in place, so a top-level `querySelector` for the root returns null and the script silently does nothing: no console error, widgets that never respond. Keep the shape above: do the work in an `init` function that runs on `DOMContentLoaded`, or at once if the document has already loaded.
- **The script never runs in wp-admin's editor.** The editor renders the block's markup without executing scripts. So the page must be complete and readable with no JS at all: the CSS default state is fully visible, every section stacked in order. The script's first act is `root.classList.add('is-js')`, and every state that only JS can leave (`opacity: 0` awaiting a scroll reveal, hidden tab panels, one slide of a carousel, a fixed-height stage) is written under `.canvas-x.is-js …`. Without the class the editor shows everything, the owner can click any text to edit it, and visitors with JS blocked still get the page. `opacity: 0` on a plain root-prefixed selector is the classic mistake: the front end looks fine and the editor shows blank space where the sections should be.
- **Telling the editor from the front end.** There is no global to test. The reliable signal is the DOM: WordPress wraps editor content in `.editor-styles-wrapper`. For anything that should differ in the editor even when the no-JS default is right (a `100vh` hero, sticky or fixed positioning, autoplaying video, a full-screen overlay), write `.editor-styles-wrapper .canvas-x …` in CSS. Never gate the whole script on it; the front end must stay fully interactive.
- **Never write a bare `<` in the script, the style, or the text.** WordPress normally runs page content through a text filter that takes any `<` (`i < 10` in JS, `5 < 6` in a paragraph) as the start of a tag running to the next `>`, and rewrites every `&` in that span to `&#038;`, so `a && b` becomes a syntax error that kills the whole script. The plugin switches that filter off on pages it renders, but feeds, other plugins, and posts on the theme's own template can still run it. Flip comparisons (`10 > i`, `n >= i`), use `!==` for loop bounds, keep `<` out of string literals, and write `&lt;` in text. A `>` on its own (`=>`, `.a > .b`) is harmless. `check-page` reports `script_ampersand_rewritten: true` when it has happened, and create/update return a `bare_lt_in_script` warning when they see one.
- **Restate what the theme styles on elements.** Block themes style `h1`–`h6`, `a`, `button`, and often `p` directly (font family, colour, text-transform, letter-spacing), and an element rule beats anything the root class only passes down by inheritance. Set font-family, colour, and text-transform explicitly on headings, links, and buttons with prefixed selectors, or the theme's display font and heading colour leak into the page (white headings on a light section is the usual symptom).
- **Editable by humans.** Keep text in ordinary headings, paragraphs, list items, links, and buttons, and images as plain `<img>` tags with `src` and `alt`; that is what the site owner can click to edit later. Text assembled by JS or hidden in attributes is not editable. Hero backgrounds: set `background-image` inline on the element and add `data-vc-bg` so the owner can swap the photo.
- **Posts** get the same block. The theme shows the post title, date, and author around it, so keep a post's root narrower in spirit: readable column, less full-bleed decoration. Set `excerpt` on posts, or the auto-excerpt will contain raw style and script text.
- **Pages show no title** on either AI-Canvas template, so the block's first section must carry the page's heading.

## Choose the template (pages)

- `framed` (default): the site's own header and footer around the block, no page title. A page that belongs to the site.
- `blank`: nothing but the block. For a standalone landing page, a campaign page with its own navigation, or anything that should not look like the rest of the site.
- `theme`: the theme's normal page layout (title, content column). Only when the user asks for a regular page.

Decide from the brief ("landing page", "standalone", "no menu", "just the page" → blank; otherwise framed). If it is a coin toss, ask in plain words: "Should this page have your site's usual header and footer, or stand on its own?" Never mention template names to the user. Posts do not take these templates; the theme's single-post layout applies.

## Workflow

1. **Find or create.** `find-pages` with a search term before creating anything the user may already have. `create-page` returns the id, public link, and wp-admin edit link.
2. **Read before you write.** `get-page` before every update; the owner or another session may have edited it since you last looked. `update-page` replaces the whole block, so always send complete content.
3. **Update** with `update-page`. WordPress records the previous content as a revision, visible to the owner under Revisions in wp-admin. A page has no revision until its first update, so the very first version can only be rebuilt from the conversation.
4. **Undo.** `list-revisions` lists revisions newest first; the newest normally matches the current content, so "undo that" is `restore-revision` with the one below it. Restoring records another revision, so a restore can itself be undone. For several steps back, pick the older revision. Restore also brings back that revision's title and excerpt.
5. **Images.** `list-media` to reuse what is already in the Media Library; `upload-media` (URL, or base64 with a filename, plus `title` and `alt`) for new files. Both return the image's dimensions and generated sizes; reference the URL of the right size verbatim, never a guessed path. Set `alt`; it is the only accessibility the image will get.
6. **Publish state.** Pages publish immediately by default; they are unlinked from menus, so visitors only reach them by address. For **posts**, ask once whether to publish now or save as a draft (`status: draft`), because publishing a post notifies subscribers and appears on the home page. Drafts are not publicly reachable, so `check-page` reports `public: false` for them.
7. **Verify** before reporting. A successful create or update proves the content saved, not that the page works.
8. **Remove** with `trash-page` when asked; it goes to the Trash in wp-admin, where the owner can restore it.

## Verify

`check-page` fetches the public page and reports:

- `public` false → the page is a draft or private, or the site returned an error.
- `alignfull_wrapper` false → the full-width wrapper is missing; the page still works but sits inside the theme's content column. Tell the user once.
- `script_was_escaped` true → the connection user cannot publish scripts. Interactivity is stripped; the site's WordPress settings need changing.
- `style_tag_present` / `script_tag_present` false when you sent them → something on the site is filtering content; report it.
- `script_ampersand_rewritten` true → a bare `<` somewhere in the block made WordPress rewrite `&` inside the script. Remove the `<`, update, re-check.

If you have browser tools, also review every meaningful change as a visitor would: open the page's link, screenshot and actually look. The content sits between the theme header and footer, your styles are applied, and the header and footer look untouched (a changed site header means a scoping violation, not a theme quirk). If the block has JS, exercise it (click the tabs, open the accordion, advance the slides) and read the console for errors. Resize to phone width for at least one check on layout-heavy pages. Fix → update → reload → re-check. Stop when the result matches what the user asked for, and report what you verified.

## Keep pages fast

Apply on the first write, not as an afterthought:

- **Right-size images.** `list-media` and `upload-media` return generated sizes; reference the smallest that covers the display area. A full-size photo in a 600px column is the most common way these pages balloon.
- **Explicit `width` and `height`** on every `<img>`, matching the size referenced, so layout does not shift as images load.
- **Lazy-load below the fold only.** `loading="lazy" decoding="async"` below the fold; the hero image stays eager with `fetchpriority="high"`.
- **Video is opt-in.** `preload="none"`, `muted playsinline` for ambient video, started and paused by an IntersectionObserver.
- **No frameworks, no client-side templating.** The markup in the block is what renders. Never read layout in a scroll handler; use IntersectionObserver for reveals and sticky states.
- **Interactive widgets are `<button>`-based** with matching ARIA state (`aria-expanded`, `aria-selected`) and proven working in the browser.
- **No data-URI images.** Put them in the Media Library.

## Working with a non-technical user

- **Report the page, not the mechanics.** A finished step is "The top section now shows your spring photo, take a look: <link>", with a screenshot when you have one. No block markup, CSS scoping, revisions, or tool names in user-facing text.
- **Translate every error.** "unfiltered_html missing" becomes "the connection to your site isn't allowed to publish interactive pages; that's a WordPress setting."
- **Vague brief: one round of questions, then build.** Ask at most once (what the page is for, must-have sections, brand colours) after checking the Media Library for logos and photos. A visible first draft beats a questionnaire.
- **Infer content; never lorem ipsum.** Write real copy from the brief, the site's existing pages, and media titles. Flag invented specifics (prices, dates, addresses, quotes) as guesses for the user to correct.
- **Say once, early:** changes go live right away, and "undo that" brings the previous version back. Mention the wp-admin edit link so they know they can tweak text and images themselves.

## Failure modes

| Signal | Meaning |
|---|---|
| `ai_canvas_not_single_html_block` | The content was not exactly one Custom HTML block; resend the whole block in the shape above |
| `ai_canvas_unfiltered_html_required` | The connection user cannot publish scripts and styles (multisite, or `DISALLOW_UNFILTERED_HTML`); the site owner or host must change it |
| `rest_cannot_*` / permission errors | The connection user cannot edit this page |
| `script_was_escaped` true in `check-page` | Same cause as `ai_canvas_unfiltered_html_required` on content written by someone else |
| `script_ampersand_rewritten` true, or `SyntaxError: Invalid or unexpected token` in the console | A bare `<` in the block; remove it |
| Script present, no console error, widgets do nothing | It queried the root before the markup existed; use the DOM-ready `init` shape |
| Sections blank in wp-admin's editor, fine on the front end | CSS hides them until JS adds a class, and the script never runs in the editor; put the hidden state under `.canvas-x.is-js` |
MD;
	}
}
