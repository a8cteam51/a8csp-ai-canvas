const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Plugin activation', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'a8csp-template-plugin' );
	} );

	test( 'the plugin is listed as active', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'plugins.php' );

		const pluginRow = page.locator(
			'tr[data-slug="a8csp-template-plugin"]'
		);
		await expect( pluginRow ).toHaveClass( /\bactive\b/ );
	} );
} );
