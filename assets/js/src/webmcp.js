import domReady from '@wordpress/dom-ready';

import { TOOLS } from './webmcp/tools';

/**
 * Registers the AI Canvas tools with the browser's WebMCP API, so an agent in the browser can
 * build the page open in the editor. The spec has moved the API from `navigator` to `document`,
 * and earlier Chrome builds still have it on `navigator`. A browser without it gets nothing.
 */
domReady( () => {
	const modelContext = document.modelContext ?? window.navigator.modelContext;
	if ( 'function' !== typeof modelContext?.registerTool ) {
		return;
	}

	for ( const tool of TOOLS ) {
		Promise.resolve()
			.then( () => modelContext.registerTool( tool ) )
			.catch( ( error ) =>
				console.error(
					`AI Canvas: could not register ${ tool.name }.`,
					error
				)
			);
	}
} );
