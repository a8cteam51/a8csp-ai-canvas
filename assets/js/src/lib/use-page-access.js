import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const pathFor = ( pageId ) => `/a8csp-ai-canvas/v1/pages/${ pageId }/access`;

/**
 * Whether a page is open to the MCP tools, and the means to change it. The state is read from
 * the plugin's route unless the caller already knows it, and a change is saved as soon as it is
 * made: it reverts if the save fails.
 *
 * @param {number}  pageId          The page ID.
 * @param {boolean} [initiallyOpen] The page's state, when the caller already has it.
 *
 * @return {{isOpen: boolean, isBusy: boolean, setOpen: (open: boolean) => Promise<void>}} The state, whether it is loading or saving, and its setter.
 */
export function usePageAccess( pageId, initiallyOpen ) {
	const isKnown = undefined !== initiallyOpen;
	const [ isOpen, setIsOpen ] = useState( isKnown ? initiallyOpen : false );
	const [ isBusy, setIsBusy ] = useState( ! isKnown );

	useEffect( () => {
		if ( isKnown || ! pageId ) {
			return undefined;
		}

		let isCurrent = true;
		setIsBusy( true );
		apiFetch( { path: pathFor( pageId ) } )
			.then( ( { open } ) => isCurrent && setIsOpen( open ) )
			.catch( () => {} )
			.finally( () => isCurrent && setIsBusy( false ) );

		return () => {
			isCurrent = false;
		};
	}, [ pageId, isKnown ] );

	const setOpen = async ( open ) => {
		setIsOpen( open );
		setIsBusy( true );

		try {
			await apiFetch( {
				path: pathFor( pageId ),
				method: 'POST',
				data: { open },
			} );
			speak(
				open
					? __( 'Page opened to AI Canvas.', 'a8csp-ai-canvas' )
					: __( 'Page closed to AI Canvas.', 'a8csp-ai-canvas' )
			);
		} catch {
			setIsOpen( ! open );
			speak(
				__( 'The change could not be saved.', 'a8csp-ai-canvas' ),
				'assertive'
			);
		} finally {
			setIsBusy( false );
		}
	};

	return { isOpen, isBusy, setOpen };
}
