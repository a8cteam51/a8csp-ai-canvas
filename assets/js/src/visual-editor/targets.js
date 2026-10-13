import { __ } from '@wordpress/i18n';

import { ATTR_EMPTY, ATTR_HOST, ATTR_RUN } from './constants';

/**
 * Works out what a click on the block's content would edit.
 *
 * A target is one of:
 * - text: an element whose content is text, edited in place; or a run of text inside an element
 *   that also holds things that are not text, such as a button with an icon;
 * - image: an `img`;
 * - link: a link with nothing to type in, such as an icon;
 * - background: an element with a CSS background image.
 *
 * @typedef {Object} Target
 * @property {'text'|'image'|'link'|'background'} kind        What the click would edit.
 * @property {Element}                            [element]   The image, link or element with the background.
 * @property {Element|null}                       [host]      For text: the element to edit, when it is edited whole.
 * @property {Element}                            [container] For text: the element that holds the text.
 * @property {{first: Node, last: Node}|null}     [run]       For text: the nodes to edit, when the container holds more than text.
 * @property {Element|null}                       [frame]     For text: the button or link the text is the whole label of, which is what gets outlined.
 * @property {Element|null}                       [link]      The link around the target, if any.
 * @property {{x: number, y: number}}             [point]     For a background: where it was clicked.
 */

/**
 * Elements that sit in a line of text.
 */
const PHRASING = new Set(
	(
		'a abbr b bdi bdo big br cite code data del dfn em font i ins kbd mark nobr q s samp small ' +
		'span strike strong sub sup time tt u var wbr'
	).split( ' ' )
);

/**
 * Phrasing elements that only say how a stretch of text reads. The text they hold belongs to
 * whatever is around them: nobody edits the bold half of a sentence on its own.
 */
const FORMATTING = new Set(
	(
		'abbr b bdi bdo big cite code data del dfn em i ins kbd mark nobr q s samp small strike strong ' +
		'sub sup time tt u var'
	).split( ' ' )
);

/**
 * Elements a click never edits.
 */
const SKIPPED =
	'script, style, noscript, template, iframe, video, audio, canvas, object, embed, input, textarea, select, option, optgroup, progress, meter, map';

const HAS_TEXT = /\S/;

const tagOf = ( node ) => node.localName.toLowerCase();

const isPhrasing = ( node ) => PHRASING.has( tagOf( node ) );

const hasText = ( node ) => HAS_TEXT.test( node.textContent );

/**
 * Whether an element has text of its own, not only text inside its children.
 *
 * @param {Element} element The element.
 *
 * @return {boolean} Whether one of its child nodes is text.
 */
function hasDirectText( element ) {
	for ( const node of element.childNodes ) {
		if ( node.nodeType === 3 && HAS_TEXT.test( node.data ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether everything inside an element sits in a line of text.
 *
 * @param {Element} element The element.
 *
 * @return {boolean} Whether all of its descendants are phrasing elements.
 */
export function isPhrasingOnly( element ) {
	for ( const descendant of element.querySelectorAll( '*' ) ) {
		if ( ! isPhrasing( descendant ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Returns the link an element is in, or is.
 *
 * @param {Element} element The element.
 * @param {Element} root    The element holding the block's content.
 *
 * @return {Element|null} The link.
 */
export function closestLink( element, root ) {
	const link = element.closest( 'a' );

	return link && link !== root && root.contains( link ) ? link : null;
}

/**
 * Finds the runs of text among an element's child nodes: each longest stretch of text and
 * phrasing elements that is not interrupted by anything else, and that has something to read.
 *
 * @param {Element} container The element.
 *
 * @return {Array<{first: Node, last: Node}>} The runs, in order.
 */
export function findRuns( container ) {
	const runs = [];
	let nodes = [];

	const close = () => {
		// A run starts and ends at something to read, not at the indentation around it.
		const isPadding = ( node ) =>
			node.nodeType === 8 ||
			( node.nodeType === 3 && ! HAS_TEXT.test( node.data ) );
		while ( nodes.length && isPadding( nodes[ 0 ] ) ) {
			nodes.shift();
		}
		while ( nodes.length && isPadding( nodes[ nodes.length - 1 ] ) ) {
			nodes.pop();
		}

		if (
			nodes.some( ( node ) => node.nodeType !== 8 && hasText( node ) )
		) {
			runs.push( { first: nodes[ 0 ], last: nodes[ nodes.length - 1 ] } );
		}
		nodes = [];
	};

	for ( const node of container.childNodes ) {
		const isInline =
			node.nodeType === 3 ||
			node.nodeType === 8 ||
			( node.nodeType === 1 &&
				isPhrasing( node ) &&
				isPhrasingOnly( node ) );

		if ( isInline ) {
			nodes.push( node );
		} else {
			close();
		}
	}
	close();

	return runs;
}

/**
 * Returns the nodes of a run.
 *
 * @param {{first: Node, last: Node}} run The run.
 *
 * @return {Node[]} Its nodes, in order.
 */
export function nodesOf( run ) {
	const nodes = [];
	for ( let node = run.first; node; node = node.nextSibling ) {
		nodes.push( node );
		if ( node === run.last ) {
			break;
		}
	}

	return nodes;
}

/**
 * Returns the bounding box of a run.
 *
 * @param {{first: Node, last: Node}} run The run.
 *
 * @return {DOMRect} The box, relative to the viewport.
 */
function rectOfRun( run ) {
	const range = run.first.ownerDocument.createRange();
	range.setStartBefore( run.first );
	range.setEndAfter( run.last );

	return range.getBoundingClientRect();
}

/**
 * Picks the run of a container that a click was aimed at.
 *
 * @param {Element}                 container The element holding the runs.
 * @param {Element}                 element   The element that was clicked: the container, or something inside one of its runs.
 * @param {{x: number, y: number}=} point     Where it was clicked.
 *
 * @return {{first: Node, last: Node}|null} The run.
 */
function pickRun( container, element, point ) {
	const runs = findRuns( container );
	if ( runs.length < 2 ) {
		return runs[ 0 ] || null;
	}

	// A click on something inside a run names the run.
	if ( element !== container ) {
		let child = element;
		while ( child.parentNode !== container ) {
			child = child.parentNode;
		}

		const run = runs.find( ( candidate ) =>
			nodesOf( candidate ).includes( child )
		);
		if ( run ) {
			return run;
		}
	}

	// A click on the container itself landed on bare text or on padding: take the run nearest to it.
	if ( ! point ) {
		return runs[ 0 ];
	}

	let nearest = runs[ 0 ];
	let nearestDistance = Infinity;
	for ( const run of runs ) {
		const rect = rectOfRun( run );
		const dx = Math.max( rect.left - point.x, 0, point.x - rect.right );
		const dy = Math.max( rect.top - point.y, 0, point.y - rect.bottom );
		const distance = dx * dx + dy * dy;
		if ( distance < nearestDistance ) {
			nearest = run;
			nearestDistance = distance;
		}
	}

	return nearest;
}

/**
 * Works out which text a click on an element would edit.
 *
 * The answer is the smallest thing that reads as one piece of text: a heading, a paragraph, a
 * button's label. A click on the bold half of a sentence edits the sentence. A click on a link
 * that is a button edits the button, not the row of buttons it sits in.
 *
 * @param {Element}                 root    The element holding the block's content.
 * @param {Element}                 element The element that was clicked.
 * @param {{x: number, y: number}=} point   Where it was clicked.
 *
 * @return {{host: Element|null, container: Element, run: {first: Node, last: Node}|null}|null} The text, or nothing when the element is not text.
 */
function resolveText( root, element, point ) {
	// Text that is being edited stays one piece until the editing ends.
	const current = element.closest( `[${ ATTR_HOST }]` );
	if ( current && root.contains( current ) ) {
		return {
			host: current,
			container: current.hasAttribute( ATTR_RUN )
				? current.parentElement
				: current,
			run: null,
		};
	}

	// An element whose text was deleted is still where its text goes.
	if ( element.hasAttribute( ATTR_EMPTY ) ) {
		return { host: element, container: element, run: null };
	}

	if ( ! hasText( element ) ) {
		return null;
	}

	let candidate = element;
	while ( ! hasDirectText( candidate ) ) {
		// A wrapper around inline elements: follow it down to the one that holds the text.
		const children = [ ...candidate.children ].filter( hasText );
		if (
			! children.every(
				( child ) => isPhrasing( child ) && isPhrasingOnly( child )
			)
		) {
			return null;
		}

		if ( children.length === 1 ) {
			candidate = children[ 0 ];
			continue;
		}
		if ( children.every( ( child ) => FORMATTING.has( tagOf( child ) ) ) ) {
			break;
		}

		return null;
	}

	for (;;) {
		const parent = candidate.parentElement;
		if ( ! parent || parent === root || ! root.contains( parent ) ) {
			break;
		}

		const tag = tagOf( candidate );
		if (
			FORMATTING.has( tag ) ||
			( PHRASING.has( tag ) && hasDirectText( parent ) )
		) {
			candidate = parent;
		} else {
			break;
		}
	}

	if ( isPhrasingOnly( candidate ) ) {
		return { host: candidate, container: candidate, run: null };
	}

	const run = pickRun( candidate, element, point );

	return run ? { host: null, container: candidate, run } : null;
}

/**
 * Returns the button or link a piece of text is the whole label of. That is the thing a person
 * sees themselves clicking, so it is what the editor outlines, whichever element inside it
 * holds the words.
 *
 * @param {Element}                                                                       root The element holding the block's content.
 * @param {{host: Element|null, container: Element, run: {first: Node, last: Node}|null}} text The text.
 *
 * @return {Element|null} The button or link, or nothing when the text is not all of one.
 */
function frameOf( root, text ) {
	const control = text.container.closest( 'a, button' );
	if ( ! control || control === root || ! root.contains( control ) ) {
		return null;
	}

	const own = text.host
		? text.host.textContent
		: nodesOf( text.run )
				.map( ( node ) => node.textContent )
				.join( '' );

	return control.textContent.trim() === own.trim() ? control : null;
}

/**
 * Returns the nearest element, from the given one upwards, that has a CSS background image.
 *
 * @param {Element} element The element.
 * @param {Element} root    The element holding the block's content.
 *
 * @return {Element|null} The element.
 */
function closestBackground( element, root ) {
	const view = element.ownerDocument.defaultView;

	for (
		let node = element;
		node && node !== root && root.contains( node );
		node = node.parentElement
	) {
		if (
			view.getComputedStyle( node ).backgroundImage.includes( 'url(' )
		) {
			return node;
		}
	}

	return null;
}

/**
 * Works out what a click on an element of the block's content would edit.
 *
 * @param {Element}                 root    The element holding the block's content.
 * @param {Element|null}            element The element that was clicked.
 * @param {{x: number, y: number}=} point   Where it was clicked, relative to the viewport.
 *
 * @return {Target|null} The target, or nothing when the click edits nothing.
 */
export function resolveTarget( root, element, point ) {
	if ( ! element || element === root || ! root.contains( element ) ) {
		return null;
	}
	if ( element.closest( SKIPPED ) ) {
		return null;
	}

	// An icon is part of whatever it sits in.
	const svg = element.closest( 'svg' );
	let start = svg ? svg.parentElement : element;
	if ( ! start || start === root || ! root.contains( start ) ) {
		return null;
	}

	if ( tagOf( start ) === 'picture' ) {
		start = start.querySelector( 'img' ) || start;
	}
	if ( tagOf( start ) === 'img' ) {
		return {
			kind: 'image',
			element: start,
			link: closestLink( start, root ),
		};
	}

	const text = resolveText( root, start, point );
	if ( text ) {
		return {
			kind: 'text',
			...text,
			frame: frameOf( root, text ),
			link: closestLink( text.container, root ),
		};
	}

	const link = closestLink( start, root );
	if ( link ) {
		return { kind: 'link', element: link, link };
	}

	const background = closestBackground( start, root );
	if ( background ) {
		return { kind: 'background', element: background, point };
	}

	return null;
}

/**
 * Whether two targets are the same thing to edit.
 *
 * @param {Target|null} a One target.
 * @param {Target|null} b Another.
 *
 * @return {boolean} Whether they are the same.
 */
export function isSameTarget( a, b ) {
	if ( ! a || ! b || a.kind !== b.kind ) {
		return false;
	}
	if ( a.kind !== 'text' ) {
		return a.element === b.element;
	}
	if ( a.host || b.host ) {
		return a.host === b.host;
	}

	return a.run.first === b.run.first && a.run.last === b.run.last;
}

/**
 * Returns the bounding box of a target.
 *
 * @param {Target} target The target.
 *
 * @return {DOMRect} The box, relative to the viewport.
 */
export function rectOf( target ) {
	if ( target.kind !== 'text' ) {
		return target.element.getBoundingClientRect();
	}
	if ( target.frame ) {
		return target.frame.getBoundingClientRect();
	}

	return target.host
		? target.host.getBoundingClientRect()
		: rectOfRun( target.run );
}

/**
 * Whether a link is drawn as a button: something with a fill or a border, and padding inside it.
 *
 * @param {Element} link The link.
 *
 * @return {boolean} Whether it looks like a button.
 */
function looksLikeButton( link ) {
	if ( link.getAttribute( 'role' ) === 'button' ) {
		return true;
	}

	const style = link.ownerDocument.defaultView.getComputedStyle( link );
	if ( style.display === 'inline' ) {
		return false;
	}

	const hasFill =
		! [ 'rgba(0, 0, 0, 0)', 'transparent' ].includes(
			style.backgroundColor
		) || style.backgroundImage !== 'none';
	const hasBorder =
		style.borderTopStyle !== 'none' &&
		parseFloat( style.borderTopWidth ) > 0;

	return ( hasFill || hasBorder ) && parseFloat( style.paddingLeft ) > 0;
}

/**
 * Names a target in the words a person would use for it.
 *
 * @param {Target}  target The target.
 * @param {Element} root   The element holding the block's content.
 *
 * @return {string} The name.
 */
export function labelFor( target, root ) {
	if ( target.kind === 'image' ) {
		return __( 'Image', 'a8csp-ai-canvas' );
	}
	if ( target.kind === 'background' ) {
		return __( 'Background image', 'a8csp-ai-canvas' );
	}

	const element = target.kind === 'link' ? target.element : target.container;
	const within = ( selector ) => {
		const match = element.closest( selector );
		return match && match !== root && root.contains( match ) ? match : null;
	};

	const control = within( 'a, button, [role="button"], label' );
	if ( control ) {
		const tag = tagOf( control );
		if ( tag === 'label' ) {
			return __( 'Label', 'a8csp-ai-canvas' );
		}
		if ( tag !== 'a' || looksLikeButton( control ) ) {
			return __( 'Button', 'a8csp-ai-canvas' );
		}

		return __( 'Link', 'a8csp-ai-canvas' );
	}

	// A paragraph is named before whatever it sits in: one in a list item is still text.
	const block = within(
		'h1, h2, h3, h4, h5, h6, p, li, blockquote, figcaption, caption, th, td'
	);
	switch ( block && tagOf( block ) ) {
		case 'h1':
		case 'h2':
		case 'h3':
		case 'h4':
		case 'h5':
		case 'h6':
			return __( 'Heading', 'a8csp-ai-canvas' );
		case 'li':
			return __( 'List item', 'a8csp-ai-canvas' );
		case 'blockquote':
			return __( 'Quote', 'a8csp-ai-canvas' );
		case 'figcaption':
		case 'caption':
			return __( 'Caption', 'a8csp-ai-canvas' );
		case 'th':
		case 'td':
			return __( 'Table cell', 'a8csp-ai-canvas' );
	}

	return __( 'Text', 'a8csp-ai-canvas' );
}
