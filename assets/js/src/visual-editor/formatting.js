import { ATTR_PAD } from './constants';

/**
 * Bold, italic and links for the text being edited, done on the DOM itself so the markup that
 * gets saved is the markup a person would write: `<strong>`, `<em>` and `<a>`, no inline styles.
 */

const TEXT = 3;

/**
 * The elements these functions made. A browser adds elements of its own to text as it is edited,
 * which are removed again; these are the ones to keep.
 */
const made = new WeakSet();

const make = ( element ) => {
	made.add( element );
	return element;
};

/**
 * Whether an element was made by one of the formatting functions.
 *
 * @param {Element} element The element.
 *
 * @return {boolean} Whether it was.
 */
export const wasMade = ( element ) => made.has( element );

/**
 * Copies an element without its content, for the other half of an element split in two.
 *
 * @param {Element} element The element.
 *
 * @return {Element} The copy.
 */
function split( element ) {
	const copy = element.cloneNode( false );
	copy.removeAttribute( 'id' );

	return make( copy );
}

/**
 * The tags each format is written and recognized as.
 */
export const FORMATS = {
	bold: { tag: 'strong', matches: [ 'strong', 'b' ] },
	italic: { tag: 'em', matches: [ 'em', 'i' ] },
};

/**
 * Returns the text nodes inside an element, in order.
 *
 * @param {Element} host The element.
 *
 * @return {Text[]} Its text nodes.
 */
function textNodesIn( host ) {
	const nodes = [];
	const walker = host.ownerDocument.createTreeWalker( host, 4 );
	for ( let node = walker.nextNode(); node; node = walker.nextNode() ) {
		nodes.push( node );
	}

	return nodes;
}

/**
 * Turns a point in the DOM into a count of the characters of the host's text that come before it.
 *
 * @param {Element} host   The element being edited.
 * @param {Node}    node   The point's node.
 * @param {number}  offset The point's offset in that node.
 *
 * @return {number} The number of characters before the point.
 */
function offsetOf( host, node, offset ) {
	const range = host.ownerDocument.createRange();
	range.selectNodeContents( host );
	try {
		range.setEnd( node, offset );
	} catch {
		return 0;
	}

	return range.toString().length;
}

/**
 * Returns where the selection sits in the host's text, as character offsets. They stay valid
 * while the elements around the text change, which is what formatting does.
 *
 * @param {Element} host The element being edited.
 *
 * @return {{start: number, end: number}|null} The offsets, or nothing when the selection is elsewhere.
 */
export function getSelectionOffsets( host ) {
	const selection = host.ownerDocument.getSelection();
	if ( ! selection || ! selection.rangeCount ) {
		return null;
	}

	const range = selection.getRangeAt( 0 );
	if (
		! host.contains( range.startContainer ) ||
		! host.contains( range.endContainer )
	) {
		return null;
	}

	return {
		start: offsetOf( host, range.startContainer, range.startOffset ),
		end: offsetOf( host, range.endContainer, range.endOffset ),
	};
}

/**
 * Builds a range over a stretch of the host's text.
 *
 * Where an offset falls between two text nodes, the start of the range goes to the node that
 * follows and the end to the node before, so the range hugs the characters it covers.
 *
 * @param {Element} host  The element being edited.
 * @param {number}  start The offset of the first character.
 * @param {number}  end   The offset past the last character.
 *
 * @return {Range} The range.
 */
export function rangeFromOffsets( host, start, end ) {
	const range = host.ownerDocument.createRange();
	range.selectNodeContents( host );
	range.collapse( true );

	const nodes = textNodesIn( host );
	const isCollapsed = start === end;
	let seen = 0;
	let hasStart = false;

	for ( const node of nodes ) {
		const length = node.length;

		if ( ! hasStart ) {
			// A caret stays at the end of the text it follows; a selection starts at the text it covers.
			const reaches = isCollapsed
				? start <= seen + length
				: start < seen + length;
			if ( reaches ) {
				range.setStart( node, start - seen );
				range.collapse( true );
				hasStart = true;
			}
		}
		if ( hasStart && end <= seen + length ) {
			range.setEnd( node, end - seen );
			return range;
		}

		seen += length;
	}

	if ( nodes.length ) {
		const last = nodes[ nodes.length - 1 ];
		if ( ! hasStart ) {
			range.setStart( last, last.length );
		}
		range.setEnd( last, last.length );
	}

	return range;
}

/**
 * Puts the selection over a stretch of the host's text.
 *
 * @param {Element} host  The element being edited.
 * @param {number}  start The offset of the first character.
 * @param {number}  end   The offset past the last character.
 */
export function setSelectionOffsets( host, start, end = start ) {
	const selection = host.ownerDocument.getSelection();
	if ( ! selection ) {
		return;
	}

	const range = rangeFromOffsets( host, start, end );
	selection.removeAllRanges();
	selection.addRange( range );
}

/**
 * Returns the stretch of text a format applies to when nothing is selected: the word the caret
 * is in or touching, or else the whole text.
 *
 * @param {Element} host   The element being edited.
 * @param {number}  offset Where the caret is.
 *
 * @return {{start: number, end: number}} The stretch.
 */
export function wordAt( host, offset ) {
	const text = host.textContent;
	const isWord = ( character ) =>
		!! character && /[\p{L}\p{N}\p{M}'’_-]/u.test( character );

	let start = offset;
	let end = offset;
	while ( start > 0 && isWord( text[ start - 1 ] ) ) {
		start--;
	}
	while ( end < text.length && isWord( text[ end ] ) ) {
		end++;
	}

	if ( start === end ) {
		const leading = text.length - text.trimStart().length;
		return { start: leading, end: text.trimEnd().length };
	}

	return { start, end };
}

/**
 * Returns the nearest element with one of the given tags that a node is inside, without leaving
 * the host.
 *
 * @param {Node}     node The node.
 * @param {string[]} tags The tag names.
 * @param {Element}  host The element being edited.
 *
 * @return {Element|null} The element.
 */
function ancestorWithin( node, tags, host ) {
	for (
		let element = node.parentElement;
		element && element !== host && host.contains( element );
		element = element.parentElement
	) {
		if ( tags.includes( element.localName.toLowerCase() ) ) {
			return element;
		}
	}

	return null;
}

/**
 * Splits the text nodes at the ends of a range, and returns the text nodes that then lie wholly
 * inside it.
 *
 * @param {Element} host  The element being edited.
 * @param {Range}   range The range.
 *
 * @return {Text[]} The text nodes the range covers.
 */
function isolateText( host, range ) {
	let { startContainer, startOffset, endContainer, endOffset } = range;

	if ( endContainer.nodeType === TEXT && endOffset < endContainer.length ) {
		endContainer.splitText( endOffset );
	}
	if ( startContainer.nodeType === TEXT && startOffset > 0 ) {
		const rest = startContainer.splitText( startOffset );
		if ( endContainer === startContainer ) {
			endContainer = rest;
			endOffset -= startOffset;
		}
		startContainer = rest;
		startOffset = 0;
	}

	const covered = host.ownerDocument.createRange();
	covered.setStart( startContainer, startOffset );
	covered.setEnd( endContainer, endOffset );

	return textNodesIn( host )
		.filter( ( node ) => node.length > 0 && covered.intersectsNode( node ) )
		.filter( ( node ) => {
			// A node the range only touches at one of its ends is not covered by it.
			const nodeRange = host.ownerDocument.createRange();
			nodeRange.selectNodeContents( node );
			return (
				covered.compareBoundaryPoints(
					0 /* START_TO_START */,
					nodeRange
				) <= 0 &&
				covered.compareBoundaryPoints(
					2 /* END_TO_END */,
					nodeRange
				) >= 0
			);
		} );
}

/**
 * Moves a node out of an element it is inside, by splitting that element around it.
 *
 * @param {Node}    node    The node to move out.
 * @param {Element} element The element to move it out of.
 */
function liftOut( node, element ) {
	// The piece of the element's content that holds the node.
	let piece = node;
	while ( piece.parentNode !== element ) {
		// The elements in between are split too, each keeping its own share.
		const parent = piece.parentNode;
		if ( piece.nextSibling ) {
			const after = split( parent );
			while ( piece.nextSibling ) {
				after.appendChild( piece.nextSibling );
			}
			parent.after( after );
		}
		if ( piece.previousSibling ) {
			const before = split( parent );
			while ( parent.firstChild !== piece ) {
				before.appendChild( parent.firstChild );
			}
			parent.before( before );
		}
		piece = parent;
	}

	if ( piece.nextSibling ) {
		const after = split( element );
		while ( piece.nextSibling ) {
			after.appendChild( piece.nextSibling );
		}
		element.after( after );
	}

	element.after( piece );
	if ( ! element.firstChild ) {
		element.remove();
	}
}

/**
 * Wraps text nodes in a new element each, sharing one element between nodes that sit side by
 * side.
 *
 * @param {Text[]}              nodes  The text nodes.
 * @param {function(): Element} create Makes the element.
 *
 * @return {Element[]} The elements made.
 */
function wrapText( nodes, create ) {
	const wrappers = [];
	let wrapper = null;

	for ( const node of nodes ) {
		if ( wrapper && wrapper.nextSibling === node ) {
			wrapper.appendChild( node );
			continue;
		}

		wrapper = make( create() );
		node.before( wrapper );
		wrapper.appendChild( node );
		wrappers.push( wrapper );
	}

	return wrappers;
}

/**
 * Tidies the host after its elements were rearranged: joins neighbouring elements that are the
 * same format, and drops the ones left with nothing in them.
 *
 * @param {Element}  host The element being edited.
 * @param {string[]} tags The tag names to tidy.
 */
function tidy( host, tags ) {
	const selector = tags.join( ', ' );

	for ( const element of host.querySelectorAll( selector ) ) {
		if ( ! element.firstChild && element.isConnected ) {
			element.remove();
		}
	}

	for ( const element of host.querySelectorAll( selector ) ) {
		if ( ! element.isConnected ) {
			continue;
		}

		let next = element.nextSibling;
		while (
			next &&
			next.nodeType === 1 &&
			next.localName === element.localName &&
			next.attributes.length === 0 &&
			element.attributes.length === 0
		) {
			element.append( ...next.childNodes );
			const following = next.nextSibling;
			next.remove();
			next = following;
		}
	}

	host.normalize();
}

/**
 * Whether a stretch of the host's text carries a format.
 *
 * @param {Element}                      host    The element being edited.
 * @param {{start: number, end: number}} offsets The stretch.
 * @param {'bold'|'italic'}              format  The format.
 *
 * @return {boolean} Whether all of the stretch carries it.
 */
export function hasFormat( host, offsets, format ) {
	const { matches } = FORMATS[ format ];

	if ( offsets.start === offsets.end ) {
		const { startContainer } = rangeFromOffsets(
			host,
			offsets.start,
			offsets.end
		);
		return (
			startContainer !== host &&
			!! ancestorWithin( startContainer, matches, host )
		);
	}

	let seen = 0;
	let found = false;
	for ( const node of textNodesIn( host ) ) {
		const from = seen;
		seen += node.length;
		if ( seen <= offsets.start || from >= offsets.end ) {
			continue;
		}
		if ( ! /\S/.test( node.data ) ) {
			continue;
		}
		if ( ! ancestorWithin( node, matches, host ) ) {
			return false;
		}
		found = true;
	}

	return found;
}

/**
 * Applies a format to a stretch of the host's text, or removes it when the whole stretch already
 * carries it.
 *
 * @param {Element}                      host    The element being edited.
 * @param {{start: number, end: number}} offsets The stretch.
 * @param {'bold'|'italic'}              format  The format.
 *
 * @return {boolean} Whether anything changed.
 */
export function toggleFormat( host, offsets, format ) {
	if ( offsets.start === offsets.end ) {
		return false;
	}

	const nodes = isolateText(
		host,
		rangeFromOffsets( host, offsets.start, offsets.end )
	);
	if ( ! nodes.length ) {
		return false;
	}

	const { tag, matches } = FORMATS[ format ];
	const isOn = hasFormat( host, offsets, format );

	if ( isOn ) {
		for ( const node of nodes ) {
			for (
				let element = ancestorWithin( node, matches, host );
				element;
				element = ancestorWithin( node, matches, host )
			) {
				liftOut( node, element );
			}
		}
	} else {
		wrapText(
			nodes.filter(
				( node ) =>
					/\S/.test( node.data ) &&
					! ancestorWithin( node, matches, host )
			),
			() => host.ownerDocument.createElement( tag )
		);
	}

	tidy( host, matches );

	return true;
}

/**
 * Returns the link a stretch of the host's text is in, when all of it is in one link inside the
 * host.
 *
 * @param {Element}                      host    The element being edited.
 * @param {{start: number, end: number}} offsets The stretch.
 *
 * @return {Element|null} The link.
 */
export function linkAt( host, offsets ) {
	const range = rangeFromOffsets( host, offsets.start, offsets.end );
	const startLink =
		range.startContainer !== host &&
		ancestorWithin( range.startContainer, [ 'a' ], host );
	if ( ! startLink ) {
		return null;
	}

	const endLink = ancestorWithin( range.endContainer, [ 'a' ], host );

	return endLink === startLink ? startLink : null;
}

/**
 * Turns a stretch of the host's text into a link. Links already inside the stretch are replaced.
 *
 * @param {Element}                      host       The element being edited.
 * @param {{start: number, end: number}} offsets    The stretch.
 * @param {Object<string, string>}       attributes The link's attributes.
 *
 * @return {Element|null} The link made, or nothing when the stretch holds no text.
 */
export function addLink( host, offsets, attributes ) {
	if ( offsets.start === offsets.end ) {
		return null;
	}

	const nodes = isolateText(
		host,
		rangeFromOffsets( host, offsets.start, offsets.end )
	);
	for ( const node of nodes ) {
		for (
			let element = ancestorWithin( node, [ 'a' ], host );
			element;
			element = ancestorWithin( node, [ 'a' ], host )
		) {
			liftOut( node, element );
		}
	}

	// One link around the whole stretch where the stretch is a row of sibling nodes, which keeps a
	// bold word inside the link from splitting it in three.
	const doc = host.ownerDocument;
	const create = () => {
		const link = doc.createElement( 'a' );
		for ( const [ name, value ] of Object.entries( attributes ) ) {
			link.setAttribute( name, value );
		}
		return link;
	};

	const covered = nodes.filter( ( node ) => node.isConnected );
	if ( ! covered.length ) {
		return null;
	}

	const whole = doc.createRange();
	whole.setStartBefore( covered[ 0 ] );
	whole.setEndAfter( covered[ covered.length - 1 ] );
	// Widen each end past the elements it is the first or last thing in.
	while (
		whole.startContainer !== host &&
		whole.startOffset === 0 &&
		whole.startContainer !== whole.commonAncestorContainer
	) {
		whole.setStartBefore( whole.startContainer );
	}
	while (
		whole.endContainer !== host &&
		whole.endOffset === whole.endContainer.childNodes.length &&
		whole.endContainer !== whole.commonAncestorContainer
	) {
		whole.setEndAfter( whole.endContainer );
	}

	let link = null;
	if ( whole.startContainer === whole.endContainer ) {
		link = make( create() );
		try {
			whole.surroundContents( link );
		} catch {
			link = null;
		}
	}
	if ( ! link ) {
		[ link ] = wrapText(
			covered.filter( ( node ) => /\S/.test( node.data ) ),
			create
		);
	}

	host.normalize();

	return link || null;
}

/**
 * Removes a link and keeps what it held.
 *
 * @param {Element} link The link.
 */
export function removeLink( link ) {
	const parent = link.parentNode;
	link.replaceWith( ...link.childNodes );
	if ( parent ) {
		parent.normalize();
	}
}

/**
 * Removes what a browser adds to text as it is edited and nobody asked for: styled spans, font
 * tags, and the like. Elements that were there before the editing started, and those the
 * formatting functions made, are kept.
 *
 * @param {Element}                    host  The element being edited.
 * @param {function(Element): boolean} keeps Whether an element belongs in the text.
 *
 * @return {boolean} Whether anything was removed.
 */
export function removeStrays( host, keeps ) {
	let changed = false;

	for ( const element of [ ...host.querySelectorAll( '*' ) ] ) {
		if ( ! element.isConnected || keeps( element ) ) {
			continue;
		}

		const tag = element.localName.toLowerCase();
		if ( tag === 'br' ) {
			continue;
		}

		if (
			FORMATS.bold.matches.includes( tag ) ||
			FORMATS.italic.matches.includes( tag ) ||
			tag === 'a'
		) {
			// A format the browser carried over, as when a bold word is typed over: it is kept as
			// if it had been made here, without the inline style a browser may give it.
			make( element );
			if ( element.hasAttribute( 'style' ) ) {
				element.removeAttribute( 'style' );
				changed = true;
			}
			continue;
		}

		element.replaceWith( ...element.childNodes );
		changed = true;
	}

	return changed;
}

/**
 * Marks the line break a browser keeps at the very end of the text being edited, and unmarks any
 * that is no longer that.
 *
 * A break at the end of an element shows nothing: browsers put it there so the caret has a line
 * to sit on, in an element with no text left for one. It is marked, not removed, because the
 * browser would only put it back. What is saved leaves it out. A break that follows another break
 * is different: that one is the empty line the text ends on, and it stays.
 *
 * @param {Element} host The element being edited.
 */
export function markPadding( host ) {
	for ( const marked of host.querySelectorAll( `[${ ATTR_PAD }]` ) ) {
		marked.removeAttribute( ATTR_PAD );
	}

	const isBlank = ( node ) => node.nodeType === TEXT && ! node.length;
	const isBreak = ( node ) =>
		!! node && node.nodeType === 1 && node.localName === 'br';

	let last = host.lastChild;
	while ( last && isBlank( last ) ) {
		last = last.previousSibling;
	}
	if ( ! isBreak( last ) ) {
		return;
	}

	let before = last.previousSibling;
	while ( before && isBlank( before ) ) {
		before = before.previousSibling;
	}
	if ( ! isBreak( before ) ) {
		last.setAttribute( ATTR_PAD, '' );
	}
}

/**
 * Turns the non-breaking spaces a browser types, where a plain space would collapse, back into
 * plain spaces.
 *
 * @param {Element} copy            The element that was edited, or a copy of it.
 * @param {boolean} keepsHardSpaces Whether the text held non-breaking spaces before it was edited. If so they are the author's, and stay.
 */
export function cleanForSave( copy, keepsHardSpaces ) {
	if ( keepsHardSpaces ) {
		return;
	}

	const walker = copy.ownerDocument.createTreeWalker( copy, 4 );
	for ( let node = walker.nextNode(); node; node = walker.nextNode() ) {
		if ( node.data.includes( '\u00a0' ) ) {
			node.data = node.data.replace( /\u00a0/g, ' ' );
		}
	}
}
