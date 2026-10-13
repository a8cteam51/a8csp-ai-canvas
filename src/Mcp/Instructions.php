<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\AICanvas\Mcp;

\defined( 'ABSPATH' ) || exit;

/**
 * The authoring guide an MCP client needs to write page content for this site: the single source
 * behind the MCP server's `instructions`, the `get-instructions` tool, and the sentences the write
 * tools carry in their descriptions.
 *
 * The text addresses the connected model, not a site visitor, so it is not translatable.
 *
 * @since   1.0.0
 * @version 2.0.0
 */
final class Instructions {
	// region METHODS

	/**
	 * Returns the rule every tool that writes to the site carries in its description: the design
	 * is worked out in the client first, and reaches the site only once the user approves it.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string
	 */
	public static function approval(): string {
		return 'Do not call this while a design is still being worked on: design and revise in Claude Design, and call this only after the user has approved the design and asked for it to go to the site.';
	}

	/**
	 * Returns the contract appended to the write tools' descriptions, for a client that reads tool
	 * descriptions but not server instructions: the shape of the content, and where a picture the
	 * site does not have goes.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public static function pointer(): string {
		return 'The content must be exactly one WordPress Custom HTML block aligned to the full width: <!-- wp:html {"align":"full"} -->, then a <style data-wp-block-html="css"> element, a <script data-wp-block-html="js"> element, and one root element with a unique class, then <!-- /wp:html -->. Vanilla HTML, CSS and JS only. These tools cannot add a picture to the site: one that list-media does not find goes in as the placeholder image named in get-instructions. Call get-instructions once per conversation before the first write for the full rules.';
	}

	/**
	 * Returns the full authoring guide as Markdown.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 *
	 * @param   string $placeholder_image_url The URL of the image this plugin ships to stand in for a picture the client cannot send.
	 *
	 * @return  string
	 */
	public static function text( string $placeholder_image_url ): string {
		$guide = <<<'MD'
# AI Canvas: publishing pages to this WordPress site

These tools publish a design to this WordPress site as a page. Each page body is one WordPress Custom HTML block holding the page's CSS, JavaScript and markup: plain HTML, CSS and JS, with no framework and no build step. WordPress stores it as an ordinary page, and the site owner can open it in the WordPress editor afterwards.

The tool names carry an `a8csp-ai-canvas-` prefix, as in `a8csp-ai-canvas-create-page`. This guide uses the short names.

## Design first, send on approval

Do the design work where you are, in Claude Design. These tools deliver a finished design to the site. They are not where a design is drafted or revised.

- **Do not write to the site until the user approves.** Call `create-page` and `update-page` only after the user has seen the design, approved it, and asked for it to go to the site. A request to design, draft, explore or revise something is not that.
- **Changes follow the same rule.** Revise the design in Claude Design, show the user, and call `update-page` once they approve the revision.
- **If you are not sure the user wants it on the site yet, ask.**
- **Reading is fine at any time**: `get-instructions`, `list-pages`, `get-page`, and `list-media` to see which images the site already has.

## The content format

The `content` you send to `create-page` and `update-page` is exactly one block in this shape:

```html
<!-- wp:html {"align":"full"} -->
<style data-wp-block-html="css">
.canvas-spring-launch { /* every selector starts with the root class */ }
</style>

<script data-wp-block-html="js">
(function () {
  function init() {
    var root = document.querySelector('.canvas-spring-launch');
    if (!root) return;
    /* behaviour here, querying from root */
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

<div class="canvas-spring-launch">
  <!-- markup -->
</div>
<!-- /wp:html -->
```

- **One block, nothing outside it.** The `<!-- wp:html {"align":"full"} -->` and `<!-- /wp:html -->` comment lines are required, written exactly like that. Content that is not exactly one Custom HTML block is rejected.
- **Always `{"align":"full"}`.** It is what makes the WordPress editor show the block across its full width, as visitors see it, and a block without it is rejected. A page you read with `get-page` may predate this rule: add it when you send the page back.
- **Order: style, script, markup.** The `data-wp-block-html` attributes are how the WordPress editor shows the CSS and JS in their own tabs. Leave out the style or script element when it would be empty.
- **A fragment, not a document.** No `<!DOCTYPE>`, `<html>`, `<head>`, `<body>`, `<title>` or `<meta>`. WordPress supplies the document.
- **Vanilla only.** No frameworks, component libraries, utility-class frameworks, JSX, CDN scripts, external stylesheets or anything that needs a build step. Convert the design to plain CSS and plain JS before sending it.
- **One root element with a unique class**, named for the page (`canvas-spring-launch`), and every CSS selector starts with that class. Never style `html`, `body`, `:root` or bare element selectors: on a page with the site header and footer, that restyles the theme. Declare custom properties on the root class.
- **Set what the theme would otherwise set.** Themes style headings, links, buttons and paragraphs directly, and an element rule beats anything the root only passes down by inheritance. Under the root class, set `font-family`, `color`, `line-height`, `margin` and `text-transform` explicitly on headings, links and buttons, and set `box-sizing: border-box` on the root and its descendants.
- **The script runs before the markup exists.** It sits above the root element, so a top-level `querySelector` for the root returns null and the script silently does nothing. Keep the shape above: one IIFE whose `init` runs on `DOMContentLoaded`, or at once if the document has already loaded, and query everything from the root element.
- **Keep the script self-contained.** DOM behaviour, animation and small interactions only. No requests to this site's authenticated endpoints, no third-party scripts, no data sent anywhere.
- **Editable by clicking.** In the WordPress editor the site owner changes a page's text, links and images by clicking on them. Keep them where a click can reach: copy as text in the markup, not written by the script or by CSS `content`; each heading, paragraph and button label in an element of its own; links as `<a href>`; pictures as `<img>`, or as a CSS `background-image` with a `url()` in the style element or a `style` attribute. Close every element you open: markup a browser has to repair cannot be edited that way.
- **Readable without JavaScript.** The WordPress editor previews the block without running its script, so anything the CSS hides until the script runs shows there as blank space. The default CSS state shows every section, and a state only the script can leave (hidden until revealed, inactive tab panels) goes under a class the script adds to the root, such as `.canvas-spring-launch.is-js`.
- **Avoid a bare `<` in the script, the style and the text.** WordPress can take a `<` that does not start a tag for one, and then corrupts the `&` characters after it, which breaks `a && b`. The two templates here switch that off, but the site owner can move the page to another template. Flip comparisons (`10 > i` in place of `i < 10`), and write `&lt;` in text.
- **The block spans the full width of the viewport** on both templates. Give each section its own inner wrapper with a `max-width` and side padding, and make the layout work from phone width up.
- **A picture is in the Media Library, or it is a placeholder.** These tools cannot add a file to the site. Call `list-media` to reuse what the Media Library already has, and reference the URLs it returns, exactly as returned. Every other picture, whether the user gave it to you, you found it on the web, or you made it, goes onto the page as this site's placeholder image: `{placeholder_image_url}`. The site owner swaps it for the real picture by clicking it in the WordPress editor. Design with the real picture in Claude Design as usual. Do not embed images as `data:` URIs or link to images on other sites. Inline SVG is fine for icons and simple illustrations.
- **Write a placeholder so a click can replace it.** Use it as the `src` of an `<img>`, or as the `url()` of a CSS `background-image`. An inline SVG or an empty box standing in for a picture cannot be swapped by clicking. Size and crop the spot in CSS as the design has it (`aspect-ratio`, `object-fit`, `object-position`), so the real picture drops into the same shape. Give the `<img>` the `width` and `height` of that shape, the `alt` text of the real picture, and no `srcset`.
- **Hand the pictures over with the page.** When you send a page that has placeholders, give the user one `.zip` of the pictures they stand for: the files the user gave you in the conversation, unchanged and under their own names, and any picture you made for the design. For a picture you only have a web address for, give the address. Tell the user which file belongs in which placeholder.
- **Keep images light.** `list-media` returns an image's generated smaller sizes: reference the smallest one that covers the space it fills. Put that size's `width` and `height` on the `<img>`, give it `alt` text, and add `loading="lazy"` to images below the first screen.
- **Accessible markup.** One `<h1>`, headings in order, real `<button>` and `<a>` elements with visible focus styles, and sufficient colour contrast.

## Templates

Pass `template` to `create-page` or `update-page`:

- `header-footer` (the default): the site's own header and footer around your content. Do not rebuild the site navigation or footer, and do not add a `<main>` element; the template provides it.
- `content-only`: nothing but your content. Use it for a standalone landing page, and include your own `<header>`, `<main>` and `<footer>` as the design needs.

Neither template prints the page title, so the content needs its own `<h1>`.

## Workflow

1. **Create** with `create-page`, once the user has approved the design. Pages are created as drafts unless you pass `status: "publish"`. Publish only when the user asks for the page to go live.
2. **Read before you change.** Call `get-page` before every `update-page`: the site owner may have edited the page since you last saw it. `update-page` replaces the whole block, so always send the complete content.
3. **Find** the pages you can work on with `list-pages`.
4. **Remove** a page with `delete-page`, only when the user asks. It moves to the Trash, where the site owner can restore it from wp-admin.
5. **Sort out the images** before you write the page: reuse what `list-media` finds, and put the placeholder everywhere else. Before you publish a page that still has placeholders, tell the user which are left and ask whether to go ahead.

You can only read, change or delete the pages `list-pages` returns: the ones created through these tools, and any the site owner has opened to you from the page editor. Every other page on the site is off limits, and the tools refuse it.

A page the site owner opened may be built from ordinary WordPress blocks, not one Custom HTML block. `update-page` still replaces its whole content with your block, so confirm with the user before you overwrite a page you did not create.

After each change, tell the user what changed and give them the page's `link` and `edit_link`. While a page is a draft, its `link` is a preview that needs a wp-admin login.
MD;

		return \strtr( $guide, array( '{placeholder_image_url}' => $placeholder_image_url ) );
	}

	// endregion
}
