import {
	ATTR_INDEX,
	ATTR_PAD,
	ATTR_RUN,
	PREFIX,
	RAW_TEXT_ELEMENTS,
	VOID_ELEMENTS,
} from './constants';
import { wasMade } from './formatting';
import { SourceDocument, escapeAttribute } from './source-document';

/**
 * Whether an element is one the editor or the browser put into the content for the time it is
 * being edited: the wrapper around a run of text, or the line break that holds the caret's line.
 * Neither has a tag in the source, and neither is saved.
 *
 * @param {Element} element The element.
 *
 * @return {boolean} Whether it is temporary.
 */
const isTemporary = ( element ) =>
	element.hasAttribute( ATTR_RUN ) || element.hasAttribute( ATTR_PAD );

/**
 * Escapes text for use between tags, as a browser does.
 *
 * @param {string} text The text.
 *
 * @return {string} The escaped text.
 */
const escapeText = ( text ) =>
	text
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /\u00a0/g, '&nbsp;' );

/**
 * Whether an element is one whose first line feed a browser drops as it reads the element's
 * content.
 *
 * @param {Element} element The element.
 *
 * @return {boolean} Whether it is a `pre`, a `textarea` or a `listing`.
 */
const dropsFirstLineFeed = ( element ) =>
	[ 'pre', 'textarea', 'listing' ].includes( element.localName );

/**
 * Pairs the block's source with the elements a browser parses it into, and writes a change made
 * to one of those elements back to the part of the source that element came from.
 *
 * Two things keep what is written honest.
 *
 * Nothing is written on trust. An element's range in the source is found by counting tags, which
 * is exact only for well-formed markup, so before the first write to a range, what would be
 * written is parsed and compared with what the range holds now. A range that does not hold up is
 * not written to.
 *
 * And the page is not taken at its word. Every element that came from the source is written with
 * its start tag as the source has it, character for character, not as the page now shows it. A
 * browser extension that adds an attribute to an element on the page, or a `details` that was
 * opened to get at its text, leaves no trace in what is saved. An attribute changes in the source
 * only when it is changed through this class.
 */
export class DomMap {
	/**
	 * @param {Document} doc The document the block is shown in.
	 */
	constructor( doc ) {
		this.doc = doc;

		// Nothing parsed here loads an image or applies a style.
		this.inert = doc.implementation.createHTMLDocument( '' );

		this.source = null;
		this.indexes = new WeakMap();
		this.implied = new WeakSet();
		this.plainSpacesIn = null;
	}

	/**
	 * The block's HTML as it now stands.
	 *
	 * @return {string} The HTML.
	 */
	get html() {
		return this.source ? this.source.html : '';
	}

	/**
	 * Parses a block's HTML into elements and pairs each one with its start tag.
	 *
	 * The HTML is parsed with every start tag numbered. If the numbers do not come back once each
	 * and in order, the browser read the markup differently from the tokenizer, or moved elements
	 * around to repair it, and the block is not one this class can write to.
	 *
	 * Scripts are in the result, and inert: a script that arrives through `innerHTML` never runs.
	 * Inline event handlers would, so they arrive under another name.
	 *
	 * @param {string} html The block's HTML.
	 *
	 * @return {DocumentFragment|null} The parsed content, or nothing when the block cannot be mapped.
	 */
	load( html ) {
		const source = new SourceDocument( html );
		if ( source.usesReservedPrefix() ) {
			return null;
		}

		const holder = this.doc.createElement( 'div' );
		holder.innerHTML = source.annotate();

		const marked = holder.querySelectorAll( `[${ ATTR_INDEX }]` );
		if ( marked.length !== source.startTags.length ) {
			return null;
		}
		for ( let i = 0; i < marked.length; i++ ) {
			if ( marked[ i ].getAttribute( ATTR_INDEX ) !== String( i ) ) {
				return null;
			}
		}

		const indexes = new WeakMap();
		const implied = new WeakSet();
		for ( const element of holder.querySelectorAll( '*' ) ) {
			if ( element.hasAttribute( ATTR_INDEX ) ) {
				indexes.set(
					element,
					Number( element.getAttribute( ATTR_INDEX ) )
				);
				element.removeAttribute( ATTR_INDEX );
			} else {
				// An element the source has no tag for, such as the `tbody` of a table written without one.
				implied.add( element );
			}
		}

		this.source = source;
		this.indexes = indexes;
		this.implied = implied;

		const fragment = this.doc.createDocumentFragment();
		fragment.append( ...holder.childNodes );

		return fragment;
	}

	/**
	 * Whether an element in the page stands for a tag in the source: it is not one the parser or
	 * the editor added, and not one made since the elements were last paired with the tags.
	 *
	 * @param {Element} element The element.
	 *
	 * @return {boolean} Whether the source has a tag for it.
	 */
	isReal( element ) {
		return (
			element.isConnected &&
			this.indexes.has( element ) &&
			! this.implied.has( element ) &&
			! isTemporary( element )
		);
	}

	/**
	 * Returns the start tag an element was parsed from.
	 *
	 * @param {Element} element The element.
	 *
	 * @return {import('./tokenizer').Tag|undefined} The tag.
	 */
	tagOf( element ) {
		return this.source.startTags[ this.indexes.get( element ) ];
	}

	/**
	 * Pairs the elements with the start tags again after the source changed, by walking both in
	 * order.
	 *
	 * @param {Element} root The element holding the block's content.
	 *
	 * @return {boolean} Whether the elements and the tags still line up.
	 */
	reindex( root ) {
		const tags = this.source.startTags;
		const walker = this.doc.createTreeWalker( root, 1 );

		let i = 0;
		for (
			let element = walker.nextNode();
			element;
			element = walker.nextNode()
		) {
			// An element the parser added has no tag, and neither has one that is only there for the editing.
			if ( this.implied.has( element ) || isTemporary( element ) ) {
				continue;
			}

			const tag = tags[ i ];
			const name = element.localName.toLowerCase();
			if (
				! tag ||
				( tag.name !== name &&
					! ( tag.name === 'image' && name === 'img' ) )
			) {
				return false;
			}
			this.indexes.set( element, i++ );
		}

		return i === tags.length;
	}

	/**
	 * Writes one node as HTML.
	 *
	 * @param {Node} node The node.
	 *
	 * @return {string} Its HTML.
	 */
	serializeNode( node ) {
		if ( node.nodeType === 3 ) {
			// A browser types a non-breaking space where a plain one would collapse. In text that had none, they go back to plain.
			return escapeText(
				this.plainSpacesIn?.contains( node )
					? node.data.replace( /\u00a0/g, ' ' )
					: node.data
			);
		}
		if ( node.nodeType === 8 ) {
			return `<!--${ node.data }-->`;
		}
		if ( node.nodeType !== 1 || node.hasAttribute( ATTR_PAD ) ) {
			return '';
		}
		if ( node.hasAttribute( ATTR_RUN ) ) {
			return this.serialize( node );
		}

		const { html } = this.source;
		const tag =
			this.indexes.has( node ) && ! this.implied.has( node )
				? this.tagOf( node )
				: null;

		if ( tag ) {
			// An element from the source keeps its start tag as the source has it.
			const open = html.slice( tag.start, tag.end );
			if ( SourceDocument.standsAlone( tag ) ) {
				return open;
			}

			const close = `</${ html.slice( tag.start + 1, tag.nameEnd ) }>`;
			if ( RAW_TEXT_ELEMENTS.has( tag.name ) && ! tag.foreign ) {
				// The text of a script or a style is never rewritten: it is copied.
				const range = this.source.innerRange( tag );
				return (
					open +
					( range
						? html.slice( range.start, range.end )
						: node.textContent ) +
					close
				);
			}

			return open + this.content( node ) + close;
		}

		const name = node.localName;
		if (
			! this.implied.has( node ) &&
			! wasMade( node ) &&
			name !== 'br'
		) {
			// Nothing of the source's and nothing the editor made: only what it holds is kept.
			return this.serialize( node );
		}

		let open = '<' + name;
		for ( const attribute of node.attributes ) {
			if ( ! attribute.name.startsWith( PREFIX ) ) {
				open += ` ${ attribute.name }="${ escapeAttribute(
					attribute.value
				) }"`;
			}
		}
		open += '>';

		return VOID_ELEMENTS.has( name )
			? open
			: open + this.serialize( node ) + `</${ name }>`;
	}

	/**
	 * Writes the child nodes of an element as HTML.
	 *
	 * @param {Element} element The element.
	 *
	 * @return {string} Their HTML.
	 */
	serialize( element ) {
		let html = '';
		for ( const child of element.childNodes ) {
			html += this.serializeNode( child );
		}

		return html;
	}

	/**
	 * Writes an element's content as the HTML to put between its tags: its text as it now reads,
	 * the elements that came from the source under the tags the source has for them, the elements
	 * made while editing as new tags, and nothing the editor or the browser only put there for the
	 * editing.
	 *
	 * A `pre` or a `textarea` whose text starts with a line feed gets one more in front: a browser
	 * drops the first line feed it reads there.
	 *
	 * @param {Element} element The element.
	 *
	 * @return {string} The element's inner HTML.
	 */
	content( element ) {
		const first = element.firstChild;
		const needsLineFeed =
			dropsFirstLineFeed( element ) &&
			!! first &&
			first.nodeType === 3 &&
			first.data.startsWith( '\n' );

		return ( needsLineFeed ? '\n' : '' ) + this.serialize( element );
	}

	/**
	 * Returns what a string of HTML parses to inside an element like the given one, written back
	 * out by the browser. Two strings that come out the same are the same content.
	 *
	 * @param {Element} element The element the HTML would be the content of.
	 * @param {string}  html    The HTML.
	 *
	 * @return {string} The browser's own writing of what the HTML parses to.
	 */
	normalize( element, html ) {
		const context = this.inert.createElementNS(
			element.namespaceURI,
			element.localName
		);

		// Parsing a fragment keeps the line feed that parsing the element in a page would drop.
		context.innerHTML = dropsFirstLineFeed( element )
			? html.replace( /^\r?\n/, '' )
			: html;

		return context.innerHTML;
	}

	/**
	 * Whether an element's content can be written back to the source: its range is found, holds
	 * exactly the element's descendants, and what would be written to it now parses to the same
	 * content it already holds.
	 *
	 * Only meaningful before the element is edited.
	 *
	 * @param {Element} element The element.
	 *
	 * @return {boolean} Whether its content can be written.
	 */
	isWritable( element ) {
		if ( ! this.isReal( element ) ) {
			return false;
		}

		const tag = this.tagOf( element );
		const range = tag && this.source.innerRange( tag );
		if ( ! range ) {
			return false;
		}

		let descendants = 0;
		for ( const descendant of element.querySelectorAll( '*' ) ) {
			if ( this.isReal( descendant ) ) {
				descendants++;
			}
		}
		if (
			this.source.countStartTags( range.start, range.end ) !== descendants
		) {
			return false;
		}

		return (
			this.normalize(
				element,
				this.source.html.slice( range.start, range.end )
			) === this.normalize( element, this.content( element ) )
		);
	}

	/**
	 * Finds the nearest element, from the given one upwards, whose content can be written back to
	 * the source. A change is saved by rewriting that element's content.
	 *
	 * @param {Element} element The element that is about to change.
	 * @param {Element} root    The element holding the block's content.
	 *
	 * @return {Element|null} The element to write, or nothing when no part of the source can safely take the change.
	 */
	findWritable( element, root ) {
		for (
			let candidate = element;
			candidate && candidate !== root;
			candidate = candidate.parentElement
		) {
			if ( this.isWritable( candidate ) ) {
				return candidate;
			}
		}

		return null;
	}

	/**
	 * Writes an element's content to its range of the source. The caller pairs the elements with
	 * the tags again afterwards.
	 *
	 * @param {Element}      element         An element `findWritable()` returned.
	 * @param {Element|null} [plainSpacesIn] The element whose text had no non-breaking spaces before it was edited, and so is written without any.
	 *
	 * @return {boolean} Whether the source changed.
	 */
	write( element, plainSpacesIn = null ) {
		const tag = this.tagOf( element );
		const range = tag && this.source.innerRange( tag );
		if ( ! range ) {
			return false;
		}

		this.plainSpacesIn = plainSpacesIn;
		const html = this.content( element );
		this.plainSpacesIn = null;

		if ( html === this.source.html.slice( range.start, range.end ) ) {
			return false;
		}

		// What the parser added on its own is written out as markup, and is in the source from here on.
		for ( const descendant of element.querySelectorAll( '*' ) ) {
			this.implied.delete( descendant );
		}

		this.source.replaceRange( range.start, range.end, html );

		return true;
	}

	/**
	 * Reads the attributes of a start tag as a browser parses them.
	 *
	 * @param {string} text    The tag's text.
	 * @param {number} nameEnd Where the tag's name ends in that text.
	 *
	 * @return {Map<string, string>|null} The attribute values by lowercased name, or nothing when the text does not parse to one element.
	 */
	readTag( text, nameEnd ) {
		// The attributes are read under a neutral name: a `td` or a `path` parsed on its own is dropped or renamed.
		const holder = this.inert.createElement( 'div' );
		holder.innerHTML = '<i' + text.slice( nameEnd );
		const parsed = holder.firstElementChild;
		if ( ! parsed || holder.childNodes.length !== 1 ) {
			return null;
		}

		const attributes = new Map();
		for ( const { name, value } of parsed.attributes ) {
			attributes.set( name.toLowerCase(), value );
		}

		return attributes;
	}

	/**
	 * Whether the attributes of an element can be changed: it has a start tag in the source, and
	 * that tag reads as one element.
	 *
	 * @param {Element} element The element.
	 *
	 * @return {boolean} Whether `setAttributes()` has something to work on.
	 */
	hasEditableTag( element ) {
		if ( ! this.isReal( element ) ) {
			return false;
		}

		const tag = this.tagOf( element );

		return !! this.readTag(
			this.source.html.slice( tag.start, tag.end ),
			tag.nameEnd - tag.start
		);
	}

	/**
	 * Changes attributes of an element and of its start tag in the source. A null value removes
	 * the attribute.
	 *
	 * The tag is rewritten and read back before anything is kept: the rewritten tag must parse to
	 * the attributes the tag had, with the changes and nothing else.
	 *
	 * @param {Element}                     element The element.
	 * @param {Object<string, string|null>} changes The attribute values by name.
	 *
	 * @return {boolean} Whether the change was made.
	 */
	setAttributes( element, changes ) {
		if ( ! this.isReal( element ) ) {
			return false;
		}

		const tag = this.tagOf( element );
		const nameEnd = tag.nameEnd - tag.start;
		const expected = this.readTag(
			this.source.html.slice( tag.start, tag.end ),
			nameEnd
		);
		const text = this.source.editTag( tag, changes );
		const actual = this.readTag( text, nameEnd );
		if ( ! expected || ! actual ) {
			return false;
		}

		for ( const [ name, value ] of Object.entries( changes ) ) {
			if ( value === null || value === undefined ) {
				expected.delete( name.toLowerCase() );
			} else {
				expected.set( name.toLowerCase(), String( value ) );
			}
		}
		if ( expected.size !== actual.size ) {
			return false;
		}
		for ( const [ name, value ] of expected ) {
			if ( actual.get( name ) !== value ) {
				return false;
			}
		}

		this.source.replaceRange( tag.start, tag.end, text );
		for ( const [ name, value ] of Object.entries( changes ) ) {
			if ( value === null || value === undefined ) {
				element.removeAttribute( name );
			} else {
				element.setAttribute( name, value );
			}
		}

		return true;
	}

	/**
	 * Returns the text of a raw text element, such as a style, as it is written in the source.
	 *
	 * @param {Element} element The element.
	 *
	 * @return {string|null} Its text, or nothing when its range is not found.
	 */
	rawText( element ) {
		const tag = this.isReal( element ) && this.tagOf( element );
		const range = tag && this.source.innerRange( tag );

		return range ? this.source.html.slice( range.start, range.end ) : null;
	}

	/**
	 * Replaces part of the text of a raw text element, in the source and in the element.
	 *
	 * @param {Element} element The element.
	 * @param {number}  start   Where the part starts in the element's text.
	 * @param {number}  end     Where the part ends.
	 * @param {string}  text    What to put there.
	 *
	 * @return {boolean} Whether the change was made.
	 */
	replaceRawText( element, start, end, text ) {
		const tag = this.isReal( element ) && this.tagOf( element );
		const range = tag && this.source.innerRange( tag );
		if ( ! range ) {
			return false;
		}

		this.source.replaceRange(
			range.start + start,
			range.start + end,
			text
		);
		element.textContent = this.rawText( element );

		return true;
	}
}
