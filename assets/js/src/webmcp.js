import domReady from '@wordpress/dom-ready';

import { registerTools } from './webmcp/register';
import { TOOLS } from './webmcp/tools';

/**
 * Registers the AI Canvas tools with the browser's WebMCP API, so an agent in the browser can
 * build the page open in the editor. The spec has moved the API from `navigator` to `document`,
 * and earlier Chrome builds still have it on `navigator`. A browser without it gets no tools, and
 * a note in the console says why, for the developer who expected them.
 */
domReady( () => {
	const modelContext = document.modelContext ?? window.navigator.modelContext;
	if ( 'function' !== typeof modelContext?.registerTool ) {
		// eslint-disable-next-line no-console -- Most browsers have no WebMCP: a note, not a warning.
		console.info(
			'AI Canvas: this browser has no WebMCP, so the page tools are not registered. In Chrome, turn on chrome://flags/#enable-webmcp-testing and relaunch.'
		);
		return;
	}

	registerTools( modelContext, TOOLS );
} );
