import { createHooks } from '@wordpress/hooks';
import domReady from '@wordpress/dom-ready';

window.a8csp_ai_webmcp = window.a8csp_ai_webmcp || {};
window.a8csp_ai_webmcp.hooks = createHooks();

domReady( () => {
	window.a8csp_ai_webmcp.hooks.doAction( 'editor.ready' );
} );
