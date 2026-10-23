import { createHooks } from '@wordpress/hooks';
import domReady from '@wordpress/dom-ready';

window.a8csp_template = window.a8csp_template || {};
window.a8csp_template.hooks = createHooks();

domReady( () => {
	window.a8csp_template.hooks.doAction( 'editor.ready' );
} );
