module.exports = require( '@a8csp/configs/node/playwright.config.base.js' )( {
	port: require( './.wp-env.json' ).port,
} );
