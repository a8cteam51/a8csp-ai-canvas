import {
	store as blockEditorStore,
	useBlockEditingMode,
} from '@wordpress/block-editor';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { Surface } from './surface';
import { SelectionToolbar } from './toolbar';

const NO_STATE = { status: 'off', selection: null };

/**
 * Returns what to tell the user when a click cannot do what it looks like it should.
 *
 * @param {string} code Which message.
 *
 * @return {string} The message.
 */
const getMessage = ( code ) =>
	( {
		locked: __(
			'This part of the page cannot be changed here. Use “Edit code” in the block toolbar to change it.',
			'a8csp-ai-canvas'
		),
		unsupported: __(
			'This block cannot be edited by clicking on it. Use “Edit code” in the block toolbar to change it.',
			'a8csp-ai-canvas'
		),
	} )[ code ];

/**
 * The key under which this browser remembers that the tip was shown.
 */
const TIP_KEY = 'a8csp-ai-canvas:visual-editor-tip';

/**
 * Whether this browser has already been shown the tip, and from now on it has.
 *
 * @return {boolean} Whether the tip was shown before.
 */
function wasTipShown() {
	try {
		if ( window.localStorage.getItem( TIP_KEY ) ) {
			return true;
		}
		window.localStorage.setItem( TIP_KEY, '1' );
		return false;
	} catch {
		// With storage switched off there is no telling, and a tip on every visit is worse than none.
		return true;
	}
}

/**
 * Returns the block's HTML when it is one string of markup, which is the only kind of content the
 * visual editor works on: not empty, and no blocks inside it.
 *
 * @param {Object|null} block The block.
 *
 * @return {string} The HTML, or an empty string.
 */
function getPlainContent( block ) {
	const content = block?.innerContent;

	return content?.length === 1 &&
		typeof content[ 0 ] === 'string' &&
		! block.innerBlocks?.length
		? content[ 0 ]
		: '';
}

/**
 * Finds a block's wrapper element. The editor's canvas is usually an iframe, so the element is
 * looked for in the frames of the page as well as in the page.
 *
 * @param {string} clientId The block's client ID.
 *
 * @return {Element|null} The element.
 */
function findBlockElement( clientId ) {
	const documents = [ document ];
	for ( const frame of document.querySelectorAll( 'iframe' ) ) {
		try {
			if ( frame.contentDocument ) {
				documents.push( frame.contentDocument );
			}
		} catch {
			// A frame from another origin holds no blocks of this editor.
		}
	}

	for ( const doc of documents ) {
		const element = doc.getElementById( 'block-' + clientId );
		if ( element ) {
			return element;
		}
	}

	return null;
}

/**
 * Runs something the visual editor does from inside the block editor's rendering, where an error
 * would take the whole editor down with it. An error is reported, and nothing more: the block is
 * then shown by the block editor alone, as if the visual editor were not there.
 *
 * @template T
 * @param {() => T} run What to run.
 *
 * @return {T|undefined} What it returned, or undefined if it failed.
 */
function safely( run ) {
	try {
		return run();
	} catch ( error ) {
		console.error( 'AI Canvas: the visual editor failed.', error );
		return undefined;
	}
}

/**
 * Makes the content of a Custom HTML block editable by pointing and clicking: text is typed over
 * where it stands, and images, links and backgrounds are changed from a toolbar beside them.
 * Every change goes into the block's HTML, so the block editor saves, undoes and redoes it like
 * any other.
 *
 * The block is left to the block editor alone when its content is empty or has blocks inside it,
 * when it is locked or shown as a preview, and for a user whose save would strip the block's CSS
 * and JavaScript: that user should not be invited to save it.
 *
 * @param {Object}  props
 * @param {string}  props.clientId   The block's client ID.
 * @param {boolean} props.isSelected Whether the block is selected.
 *
 * @return {Element|null} The toolbar for what is selected, when something is.
 */
export function VisualEditor( { clientId, isSelected } ) {
	const registry = useRegistry();
	const editingMode = useBlockEditingMode();
	const { source, isAllowed } = useSelect(
		( select ) => {
			const { getBlock, getSettings } = select( blockEditorStore );
			const settings = getSettings();
			const html = getPlainContent( getBlock( clientId ) );

			return {
				source: html,
				isAllowed:
					! settings.isPreviewMode &&
					( !! settings.__experimentalCanUserUseUnfilteredHTML ||
						! /<(style|script)[\s>]/i.test( html ) ),
			};
		},
		[ clientId ]
	);
	const { createInfoNotice, createWarningNotice } =
		useDispatch( noticesStore );

	const [ state, setState ] = useState( NO_STATE );
	const [ panel, setPanel ] = useState( null );
	const surfaceRef = useRef( null );

	const isEnabled =
		isAllowed && editingMode !== 'disabled' && !! source.trim();

	useEffect( () => {
		if ( ! isEnabled ) {
			return undefined;
		}

		const block = findBlockElement( clientId );
		const container = block?.querySelector(
			':scope > .block-editor-inner-content'
		);
		if ( ! container ) {
			return undefined;
		}

		const { getBlock } = registry.select( blockEditorStore );
		const { updateBlock } = registry.dispatch( blockEditorStore );

		const surface = safely(
			() =>
				new Surface( {
					block,
					container,
					getSource: () => getPlainContent( getBlock( clientId ) ),
					onChange: ( html ) =>
						updateBlock( clientId, { innerContent: [ html ] } ),
					onState: setState,
					onNotice: ( code ) =>
						createWarningNotice( getMessage( code ), {
							type: 'snackbar',
							id: 'a8csp-ai-canvas-visual-editor-' + code,
						} ),
					onLink: () => setPanel( 'link' ),
				} )
		);
		if ( ! surface ) {
			return undefined;
		}
		surfaceRef.current = surface;

		return () => {
			surfaceRef.current = null;
			safely( () => surface.destroy() );
			setState( NO_STATE );
			setPanel( null );
		};
	}, [ clientId, isEnabled, registry, createWarningNotice ] );

	// An undo, a redo, or a change made in the code editor: show the block as it now is.
	useEffect( () => {
		safely( () => surfaceRef.current?.sync() );
	}, [ source ] );

	useEffect( () => {
		if ( ! isSelected ) {
			safely( () => surfaceRef.current?.select( null ) );
		}
	}, [ isSelected ] );

	const { status, selection } = state;

	// The panels belong to what is selected, and close when the selection moves on.
	const selected = selection?.element;
	useEffect( () => {
		setPanel( null );
	}, [ selected ] );

	// The block's own toolbar steps aside for the toolbar of what is selected inside the block.
	const hasSelection = !! selection;
	useEffect( () => {
		if ( ! hasSelection ) {
			return undefined;
		}

		document.body.classList.add( 'a8csp-ve-has-selection' );
		return () => document.body.classList.remove( 'a8csp-ve-has-selection' );
	}, [ hasSelection ] );

	useEffect( () => {
		if ( status === 'ready' && ! wasTipShown() ) {
			createInfoNotice(
				__(
					'Tip: click any text, image or button on the page to change it.',
					'a8csp-ai-canvas'
				),
				{ type: 'snackbar', id: 'a8csp-ai-canvas-visual-editor-tip' }
			);
		}
	}, [ status, createInfoNotice ] );

	if ( ! selection || ! surfaceRef.current ) {
		return null;
	}

	return (
		<SelectionToolbar
			selection={ selection }
			surface={ surfaceRef.current }
			panel={ panel }
			setPanel={ setPanel }
		/>
	);
}
