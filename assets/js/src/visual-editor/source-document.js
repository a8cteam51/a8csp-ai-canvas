import { ATTR_INDEX, PREFIX, VOID_ELEMENTS } from './constants';
import { tokenize } from './tokenizer';

const isSpace = ( code ) =>
	code === 32 || code === 9 || code === 10 || code === 12 || code === 13;

/**
 * Escapes a string for use as a double-quoted attribute value.
 *
 * @param {string} value The value.
 *
 * @return {string} The escaped value.
 */
export function escapeAttribute( value ) {
	return String( value )
		.replace( /&/g, '&amp;' )
		.replace( /"/g, '&quot;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /\u00a0/g, '&nbsp;' );
}

/**
 * The block's HTML as a string, with the position of every tag in it, so that one element can be
 * changed by rewriting only the characters that make it up. Everything else in the source stays
 * as it was written, byte for byte.
 */
export class SourceDocument {
	/**
	 * @param {string} html The block's HTML.
	 */
	constructor( html ) {
		this.html = html;
		this.read();
	}

	/**
	 * Finds the tags of the current HTML again, after it changed.
	 */
	read() {
		this.tags = tokenize( this.html );
		this.startTags = [];

		this.tags.forEach( ( tag, position ) => {
			tag.position = position;
			if ( tag.type === 'start' ) {
				tag.index = this.startTags.length;
				this.startTags.push( tag );
			}
		} );
	}

	/**
	 * Whether the HTML already uses the editor's own attribute prefix, in which case the marks
	 * the editor adds could not be told apart from the block's markup.
	 *
	 * @return {boolean} Whether the prefix is in use.
	 */
	usesReservedPrefix() {
		return this.html.toLowerCase().includes( PREFIX );
	}

	/**
	 * Returns a copy of the HTML for the editor to parse: every start tag carries its index, and
	 * every inline event handler is renamed to an attribute that does nothing.
	 *
	 * Nothing is removed. A tag the tokenizer got wrong then shows up as a mark that is missing
	 * from the parsed elements, which is what the caller checks for.
	 *
	 * @return {string} The annotated HTML.
	 */
	annotate() {
		const { html } = this;
		let out = '';
		let cursor = 0;

		for ( const tag of this.startTags ) {
			out +=
				html.slice( cursor, tag.nameEnd ) +
				` ${ ATTR_INDEX }="${ tag.index }"`;
			cursor = tag.nameEnd;

			for ( const attribute of tag.attrs ) {
				if ( attribute.name.startsWith( 'on' ) ) {
					out += html.slice( cursor, attribute.start ) + PREFIX;
					cursor = attribute.start;
				}
			}
		}

		return out + html.slice( cursor );
	}

	/**
	 * Whether a start tag has no end tag: a void element, or an SVG or MathML tag that closes
	 * itself.
	 *
	 * @param {import('./tokenizer').Tag} tag The start tag.
	 *
	 * @return {boolean} Whether it stands alone.
	 */
	static standsAlone( tag ) {
		return tag.foreign ? tag.selfClosing : VOID_ELEMENTS.has( tag.name );
	}

	/**
	 * Finds the range of the HTML between a start tag and its end tag, by counting the tags of
	 * the same name in between. That is exact for well-formed markup and a guess for anything
	 * else, so the caller compares the range against the browser's parse before writing to it.
	 *
	 * @param {import('./tokenizer').Tag} tag The start tag.
	 *
	 * @return {{start: number, end: number}|null} The range, or nothing when the element has no content of its own.
	 */
	innerRange( tag ) {
		if ( SourceDocument.standsAlone( tag ) ) {
			return null;
		}

		let depth = 1;
		for ( let i = tag.position + 1; i < this.tags.length; i++ ) {
			const other = this.tags[ i ];
			if ( other.name !== tag.name ) {
				continue;
			}

			if ( other.type === 'start' ) {
				if ( ! SourceDocument.standsAlone( other ) ) {
					depth++;
				}
			} else if ( --depth === 0 ) {
				return { start: tag.end, end: other.start };
			}
		}

		return null;
	}

	/**
	 * Counts the start tags that open inside a range.
	 *
	 * @param {number} start Where the range starts.
	 * @param {number} end   Where the range ends.
	 *
	 * @return {number} The number of start tags.
	 */
	countStartTags( start, end ) {
		let count = 0;
		for ( const tag of this.startTags ) {
			if ( tag.start >= end ) {
				break;
			}
			if ( tag.start >= start ) {
				count++;
			}
		}

		return count;
	}

	/**
	 * Replaces a range of the HTML.
	 *
	 * @param {number} start Where the range starts.
	 * @param {number} end   Where the range ends.
	 * @param {string} text  What to put there.
	 */
	replaceRange( start, end, text ) {
		this.html = this.html.slice( 0, start ) + text + this.html.slice( end );
		this.read();
	}

	/**
	 * Returns the text of a start tag with some of its attributes changed: the value set for
	 * those that exist, those that do not added, and those given as null removed. The rest of the
	 * tag is as written.
	 *
	 * @param {import('./tokenizer').Tag}   tag     The start tag.
	 * @param {Object<string, string|null>} changes The attribute values by name.
	 *
	 * @return {string} The tag's new text.
	 */
	editTag( tag, changes ) {
		const { html } = this;
		const edits = [];
		let additions = '';

		for ( const [ name, value ] of Object.entries( changes ) ) {
			const matches = tag.attrs.filter(
				( attribute ) => attribute.name === name.toLowerCase()
			);

			if ( value === null || value === undefined ) {
				for ( const attribute of matches ) {
					let start = attribute.start;
					while (
						start > tag.nameEnd &&
						isSpace( html.charCodeAt( start - 1 ) )
					) {
						start--;
					}
					edits.push( { start, end: attribute.end, text: '' } );
				}
				continue;
			}

			// A browser reads the first of two attributes with one name, and so does this.
			const [ attribute ] = matches;
			const quoted = `"${ escapeAttribute( value ) }"`;
			if ( ! attribute ) {
				additions += ` ${ name }=${ quoted }`;
			} else if ( attribute.valueStart === -1 ) {
				edits.push( {
					start: attribute.nameEnd,
					end: attribute.end,
					text: '=' + quoted,
				} );
			} else {
				edits.push( {
					start: attribute.valueStart - attribute.quote.length,
					end: attribute.end,
					text: quoted,
				} );
			}
		}

		// The edits are applied from the end of the tag backwards, so none moves another.
		edits.sort( ( a, b ) => b.start - a.start );

		if ( additions ) {
			// New attributes go after the last thing in the tag, ahead of any space before its `>` or `/>`.
			// That is at or past every other edit, so they go in first.
			let at = tag.end - ( tag.selfClosing ? 2 : 1 );
			while ( at > tag.nameEnd && isSpace( html.charCodeAt( at - 1 ) ) ) {
				at--;
			}
			edits.unshift( { start: at, end: at, text: additions } );
		}

		let text = html.slice( tag.start, tag.end );
		for ( const edit of edits ) {
			text =
				text.slice( 0, edit.start - tag.start ) +
				edit.text +
				text.slice( edit.end - tag.start );
		}

		return text;
	}

	/**
	 * Changes attributes of one start tag in the HTML.
	 *
	 * @param {import('./tokenizer').Tag}   tag     The start tag.
	 * @param {Object<string, string|null>} changes The attribute values by name.
	 */
	setAttributes( tag, changes ) {
		const text = this.editTag( tag, changes );
		if ( text !== this.html.slice( tag.start, tag.end ) ) {
			this.replaceRange( tag.start, tag.end, text );
		}
	}
}
