import { FormToggle } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { usePageAccess } from './lib/use-page-access';

/**
 * A page's switch in the Pages list's AI Canvas column: whether the MCP tools may work on it.
 *
 * @param {Object}  props               Component props.
 * @param {number}  props.pageId        The page ID.
 * @param {string}  props.title         The page's title, for the switch's accessible label.
 * @param {boolean} props.initiallyOpen Whether the page was open when the list rendered.
 *
 * @return {Element} The switch.
 */
function AccessSwitch( { pageId, title, initiallyOpen } ) {
	const { isOpen, isBusy, setOpen } = usePageAccess( pageId, initiallyOpen );

	return (
		<FormToggle
			checked={ isOpen }
			disabled={ isBusy }
			onChange={ () => setOpen( ! isOpen ) }
			aria-label={ sprintf(
				/* translators: %s: A page's title */
				__( 'Let AI Canvas edit “%s”', 'a8csp-ai-canvas' ),
				title
			) }
		/>
	);
}

/**
 * Draws a switch in every AI Canvas cell that does not have one yet.
 */
function mountSwitches() {
	document
		.querySelectorAll( '.a8csp-ai-canvas-access:not([data-mounted])' )
		.forEach( ( container ) => {
			container.dataset.mounted = '1';
			createRoot( container ).render(
				<AccessSwitch
					pageId={ Number( container.dataset.pageId ) }
					title={ container.dataset.title }
					initiallyOpen={ '1' === container.dataset.open }
				/>
			);
		} );
}

domReady( () => {
	mountSwitches();

	// Quick Edit replaces a page's whole row with fresh markup from the server.
	const list = document.getElementById( 'the-list' );
	if ( list ) {
		new window.MutationObserver( mountSwitches ).observe( list, {
			childList: true,
		} );
	}
} );
