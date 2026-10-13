import { ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { PluginPostStatusInfo, store as editorStore } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

import { usePageAccess } from './lib/use-page-access';

/**
 * The page editor's switch for whether the MCP tools may work on the page, shown with the page's
 * other settings in the sidebar's summary. It saves as soon as it is flipped, on its own: it is
 * not part of the page, and does not wait for the page to be saved.
 *
 * @param {Object} props        Component props.
 * @param {number} props.pageId The page ID.
 *
 * @return {Element} The switch.
 */
function CanvasAccessSwitch( { pageId } ) {
	const { isOpen, isBusy, setOpen } = usePageAccess( pageId );

	return (
		<PluginPostStatusInfo>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __(
					'Let AI Canvas edit this page',
					'a8csp-ai-canvas'
				) }
				help={
					isOpen
						? __(
								'A connected MCP client can read this page, replace its content, and move it to the Trash.',
								'a8csp-ai-canvas'
							)
						: __(
								'Connected MCP clients cannot see or change this page.',
								'a8csp-ai-canvas'
							)
				}
				checked={ isOpen }
				disabled={ isBusy }
				onChange={ setOpen }
			/>
		</PluginPostStatusInfo>
	);
}

/**
 * Shows the switch when the document being edited is a page.
 *
 * @return {Element|null} The switch, or nothing for any other document.
 */
function CanvasAccess() {
	const { isPage, pageId } = useSelect( ( select ) => {
		const { getCurrentPostType, getCurrentPostId } = select( editorStore );

		return {
			isPage: 'page' === getCurrentPostType(),
			pageId: getCurrentPostId(),
		};
	}, [] );

	// The key gives each page its own state as the site editor moves from one to the next.
	return isPage && pageId ? (
		<CanvasAccessSwitch key={ pageId } pageId={ pageId } />
	) : null;
}

registerPlugin( 'a8csp-ai-canvas', { render: CanvasAccess } );
