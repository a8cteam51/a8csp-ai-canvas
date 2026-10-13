const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Page access switches', () => {
	let pageId;

	// The plugin header requires MCP Adapter: WordPress refuses to activate the plugin without it.
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'mcp-adapter' );
		await requestUtils.activatePlugin( 'a8csp-ai-canvas' );

		// The content keeps the editor from opening on its starter-pattern chooser, which an empty page gets.
		const page = await requestUtils.createPage( {
			title: 'Access proof',
			status: 'publish',
			content:
				'<!-- wp:paragraph --><p>Access proof.</p><!-- /wp:paragraph -->',
		} );
		pageId = page.id;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPages();
	} );

	test( 'the Pages list switch opens a page, and the editor switch closes it', async ( {
		admin,
		page,
		editor,
	} ) => {
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );

		const listSwitch = page.getByRole( 'checkbox', {
			name: 'Let AI Canvas edit “Access proof”',
		} );
		await expect( listSwitch ).not.toBeChecked();

		await listSwitch.check();
		await expect( listSwitch ).toBeEnabled();

		// The switch saved on its own: the state is still there after a reload.
		await page.reload();
		await expect( listSwitch ).toBeChecked();

		await admin.editPost( pageId );
		await editor.openDocumentSettingsSidebar();

		const editorSwitch = page.getByRole( 'checkbox', {
			name: 'Let AI Canvas edit this page',
		} );
		await expect( editorSwitch ).toBeChecked();

		await editorSwitch.uncheck();
		await expect( editorSwitch ).toBeEnabled();

		await admin.visitAdminPage( 'edit.php', 'post_type=page' );
		await expect( listSwitch ).not.toBeChecked();
	} );
} );
