const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

// A one-pixel image, so the page needs nothing from the Media Library to have an image to click.
const PIXEL =
	'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

// Written the way a model writes a page, quirks included: the source is expected back byte for byte around each change.
const MARKUP = `
<style data-wp-block-html="css">
.canvas-proof, .canvas-proof * { box-sizing: border-box; }
.canvas-proof { padding: 24px; font-family: system-ui, sans-serif; color: #14213d; }
.canvas-proof .btn { display: inline-block; padding: 12px 20px; background: #14213d; color: #fff; text-decoration: none; }
.canvas-proof img { display: block; width: 200px; height: 100px; background: #fca311; }
</style>

<script data-wp-block-html="js">
(function () {
  function init() {
    var root = document.querySelector('.canvas-proof');
    if (root) { root.classList.add('is-js'); }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
</script>

<div class="canvas-proof">
  <h1 CLASS='title'>Fresh bread</h1>
  <p class="lead">Baked   daily &amp; delivered. Read <a href="/story/" onclick="track('story')">our story</a>.</p>
  <a class="btn" href="/order/">Order now <svg viewBox="0 0 10 10" width="10" height="10" aria-hidden="true"><path d="M0 5h10"/></svg></a>
  <img src="${ PIXEL }" width="200" height="100" alt="A loaf">
</div>
`;

const CONTENT =
	'<!-- wp:html {"align":"full"} -->' + MARKUP + '<!-- /wp:html -->';

test.describe( 'Visual editor of the Custom HTML block', () => {
	let pageId;

	// The plugin header requires MCP Adapter: WordPress refuses to activate the plugin without it.
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'mcp-adapter' );
		await requestUtils.activatePlugin( 'a8csp-ai-canvas' );
	} );

	test.beforeEach( async ( { requestUtils, admin } ) => {
		const page = await requestUtils.createPage( {
			title: 'Visual editor proof',
			status: 'publish',
			template: 'ai-canvas-content-only',
			content: CONTENT,
		} );
		pageId = page.id;

		await admin.editPost( pageId );
	} );

	// Only the page this suite made: the suite can then run against a site that has pages of its own.
	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/pages/${ pageId }`,
			params: { force: true },
		} );
	} );

	// Puts the selection in the first text node of an element that is being edited.
	const selectText = ( locator, start, end = start ) =>
		locator.evaluate(
			( element, [ from, to ] ) => {
				const range = element.ownerDocument.createRange();
				range.setStart( element.firstChild, from );
				range.setEnd( element.firstChild, to );

				const selection = element.ownerDocument.getSelection();
				selection.removeAllRanges();
				selection.addRange( range );
			},
			[ start, end ]
		);

	test( 'typing over a heading changes that heading in the source and nothing else', async ( {
		editor,
		page,
		requestUtils,
	} ) => {
		const heading = editor.canvas.locator( '.canvas-proof h1' );

		await heading.click();
		await expect( heading ).toHaveAttribute( 'contenteditable', 'true' );

		await selectText( heading, 5, 11 );
		await page.keyboard.type( ' rye' );

		await expect
			.poll( () => editor.getEditedPostContent() )
			.toBe(
				CONTENT.replace(
					"<h1 CLASS='title'>Fresh bread</h1>",
					"<h1 CLASS='title'>Fresh rye</h1>"
				)
			);

		// Saved from the keyboard with the caret still in the text.
		await page.keyboard.press( 'ControlOrMeta+s' );
		await expect(
			page
				.getByRole( 'button', { name: 'Dismiss this notice' } )
				.filter( {
					hasText: 'Page updated.',
				} )
		).toBeVisible();

		const saved = await requestUtils.rest( {
			path: `/wp/v2/pages/${ pageId }`,
			params: { context: 'edit' },
		} );
		expect( saved.content.raw ).toContain(
			"<h1 CLASS='title'>Fresh rye</h1>"
		);
		expect( saved.content.raw ).toContain( 'onclick="track(\'story\')"' );
		expect( saved.content.raw ).toContain( "root.classList.add('is-js')" );
	} );

	test( 'the toolbar of a stretch of selected text makes it bold, and undo takes it back', async ( {
		editor,
		page,
	} ) => {
		const lead = editor.canvas.locator( '.canvas-proof .lead' );

		await lead.click( { position: { x: 4, y: 4 } } );

		// Only typing so far: no toolbar gets between the text and what is above it.
		await expect(
			page.getByRole( 'toolbar', { name: 'Text' } )
		).toBeHidden();

		await selectText( lead, 8, 13 );
		await page
			.getByRole( 'toolbar', { name: 'Text' } )
			.getByRole( 'button', { name: 'Bold' } )
			.click();

		// The handler on the link next to it is still in the source, and the spacing is as written.
		await expect
			.poll( () => editor.getEditedPostContent() )
			.toContain(
				'<p class="lead">Baked   <strong>daily</strong> &amp; delivered. Read <a href="/story/" onclick="track(\'story\')">our story</a>.</p>'
			);

		await page.keyboard.press( 'ControlOrMeta+z' );
		await expect
			.poll( () => editor.getEditedPostContent() )
			.toBe( CONTENT );
	} );

	test( 'a button says where it goes, and its link can be changed', async ( {
		editor,
		page,
	} ) => {
		await editor.canvas
			.locator( '.canvas-proof .btn' )
			.click( { position: { x: 30, y: 20 } } );

		const toolbar = page.getByRole( 'toolbar', { name: 'Button' } );
		await expect(
			toolbar.getByRole( 'button', { name: 'Change link' } )
		).toHaveText( '/order/' );

		await toolbar.getByRole( 'button', { name: 'Change link' } ).click();
		await page.getByPlaceholder( 'Search or type URL' ).fill( '/shop/' );
		await page.keyboard.press( 'Enter' );

		// The icon beside the label is written as it was.
		await expect
			.poll( () => editor.getEditedPostContent() )
			.toBe(
				CONTENT.replace(
					'<a class="btn" href="/order/">',
					'<a class="btn" href="/shop/">'
				)
			);
	} );

	test( 'an image gets a new alternative text', async ( {
		editor,
		page,
	} ) => {
		await editor.canvas.locator( '.canvas-proof img' ).click();

		await page
			.getByRole( 'toolbar', { name: 'Image' } )
			.getByRole( 'button', { name: 'Alt text' } )
			.click();
		await page
			.getByRole( 'textbox', { name: 'Alternative text' } )
			.fill( 'A rye loaf on a board' );
		await page.getByRole( 'button', { name: 'Done' } ).click();

		await expect
			.poll( () => editor.getEditedPostContent() )
			.toBe(
				CONTENT.replace( 'alt="A loaf"', 'alt="A rye loaf on a board"' )
			);
	} );

	test( 'the page’s script and inline handlers do not run in the editor', async ( {
		editor,
	} ) => {
		const root = editor.canvas.locator( '.canvas-proof' );

		await expect( root ).toBeVisible();
		await expect( root ).not.toHaveClass( /is-js/ );
		await expect( root.locator( '[onclick]' ) ).toHaveCount( 0 );

		// A click on a link edits it; it does not follow it.
		await root.locator( '.lead a' ).click();
		await expect( root.locator( '.lead' ) ).toHaveAttribute(
			'contenteditable',
			'true'
		);
	} );
} );
