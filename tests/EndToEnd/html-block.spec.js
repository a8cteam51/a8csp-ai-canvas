const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Custom HTML block alignment', () => {
	let pageId;

	// The plugin header requires MCP Adapter: WordPress refuses to activate the plugin without it.
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'mcp-adapter' );
		await requestUtils.activatePlugin( 'a8csp-ai-canvas' );

		// The second block is the control: without the alignment, the editor keeps a block in the content column.
		const page = await requestUtils.createPage( {
			title: 'Alignment proof',
			status: 'publish',
			template: 'ai-canvas-header-footer',
			content:
				'<!-- wp:html {"align":"full"} -->\n<div>Full width</div>\n<!-- /wp:html -->\n\n' +
				'<!-- wp:html -->\n<div>Content column</div>\n<!-- /wp:html -->',
		} );
		pageId = page.id;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPages();
	} );

	test( 'a full-width block fills the editor, and the editor keeps its alignment', async ( {
		admin,
		editor,
	} ) => {
		await admin.editPost( pageId );

		const blocks = editor.canvas.locator( '[data-type="core/html"]' );
		await expect( blocks ).toHaveCount( 2 );

		const canvasWidth = () =>
			editor.canvas
				.locator( 'body' )
				.evaluate( ( body ) => body.clientWidth );
		const widthOf = async ( block ) =>
			Math.round( ( await block.boundingBox() ).width );

		await expect
			.poll(
				async () =>
					( await widthOf( blocks.first() ) ) -
					( await canvasWidth() )
			)
			.toBe( 0 );
		expect( await widthOf( blocks.last() ) ).toBeLessThan(
			await canvasWidth()
		);

		// The attribute is registered, so the editor writes it back out instead of dropping it.
		expect( await editor.getEditedPostContent() ).toContain(
			'<!-- wp:html {"align":"full"} -->'
		);
	} );
} );
