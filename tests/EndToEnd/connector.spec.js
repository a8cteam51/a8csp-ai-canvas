const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const toHeader = ( username, password ) =>
	'Basic ' +
	Buffer.from( `${ username }:${ password }` ).toString( 'base64' );

test.describe( 'Connectors card', () => {
	// The plugin header requires MCP Adapter: WordPress refuses to activate the plugin without it.
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'mcp-adapter' );
		await requestUtils.activatePlugin( 'a8csp-ai-canvas' );
		await requestUtils.createUser( {
			username: 'a8csp-e2e-editor',
			email: 'a8csp-e2e-editor@example.test',
			password: 'a8csp-e2e-editor-password',
			roles: [ 'editor' ],
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllUsers();
	} );

	test( 'turns a chosen user’s application password into the Authorization header', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'options-connectors.php' );

		const card = page.getByRole( 'group', { name: 'AI Canvas' } );
		await card.getByRole( 'button', { name: 'Set up' } ).click();

		await expect(
			card.getByLabel( 'Server URL', { exact: true } )
		).toHaveValue( /\/a8csp-ai-canvas\/mcp$/ );

		const header = card.getByLabel( 'Authorization header', {
			exact: true,
		} );
		await expect( header ).toHaveValue( '' );

		const password = 'abcd EFGH 1234 ijkl MNOP 5678';
		await card.getByLabel( 'Application password' ).fill( password );

		// wp-env signs the tests in as its default administrator, `admin`, who is preselected.
		await expect( header ).toHaveValue( toHeader( 'admin', password ) );

		await card
			.getByLabel( 'Connect as' )
			.selectOption( 'a8csp-e2e-editor' );
		await expect( header ).toHaveValue(
			toHeader( 'a8csp-e2e-editor', password )
		);
	} );
} );
