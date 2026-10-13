import { RAW_TEXT_ELEMENTS } from './constants';

/**
 * Finds every start tag and end tag in a string of HTML, with the offsets of each tag and of each
 * of its attributes.
 *
 * It follows the HTML tokenizer closely enough to agree with a browser on well-formed markup:
 * comments, the elements whose content is read as text, attribute values that hold a `>`, and
 * inline SVG and MathML, where a `/>` does close a tag and a `<style>` holds markup. It does not
 * build a tree, and it does not have to be right about markup a browser would repair: whoever uses
 * the offsets checks them against the browser's own parse first.
 *
 * @typedef {Object} Attribute
 * @property {string}        name        The attribute's name, lowercased.
 * @property {number}        start       Where the name starts.
 * @property {number}        nameEnd     Where the name ends.
 * @property {number}        valueStart  Where the value starts, inside any quotes, or -1 with no value.
 * @property {number}        valueEnd    Where the value ends, inside any quotes, or -1 with no value.
 * @property {string}        quote       The quote character around the value, or an empty string.
 * @property {number}        end         Where the attribute ends, past any closing quote.
 *
 * @typedef {Object} Tag
 * @property {'start'|'end'} type        Which kind of tag it is.
 * @property {string}        name        The tag's name, lowercased.
 * @property {number}        start       Where its `<` is.
 * @property {number}        nameEnd     Where its name ends.
 * @property {number}        end         The offset just past its `>`.
 * @property {Attribute[]}   attrs       Its attributes, in source order.
 * @property {boolean}       selfClosing Whether it ends in `/>`.
 * @property {boolean}       foreign     Whether it is an SVG or MathML tag.
 */

const SVG_INTEGRATION_POINTS = new Set( [ 'foreignobject', 'desc', 'title' ] );
const MATHML_INTEGRATION_POINTS = new Set( [
	'mi',
	'mo',
	'mn',
	'ms',
	'mtext',
] );

/**
 * The HTML start tags that end an SVG or MathML subtree they turn up in, where a browser carries
 * on as if the subtree had been closed.
 */
const FOREIGN_BREAKOUTS = new Set(
	(
		'b big blockquote body br center code dd div dl dt em embed h1 h2 h3 h4 h5 h6 head hr i img li ' +
		'listing menu meta nobr ol p pre ruby s small span strong strike sub sup table tt u ul var'
	).split( ' ' )
);

const isAlpha = ( code ) =>
	( code >= 65 && code <= 90 ) || ( code >= 97 && code <= 122 );

const isSpace = ( code ) =>
	code === 32 || code === 9 || code === 10 || code === 12 || code === 13;

/**
 * Reads one tag.
 *
 * @param {string}        html      The HTML.
 * @param {'start'|'end'} type      The kind of tag.
 * @param {number}        open      Where the tag's `<` is.
 * @param {number}        nameStart Where the tag's name starts.
 *
 * @return {Tag|null} The tag, or nothing when the HTML ends inside it.
 */
function readTag( html, type, open, nameStart ) {
	const length = html.length;
	let i = nameStart;

	while ( i < length ) {
		const code = html.charCodeAt( i );
		if ( isSpace( code ) || code === 47 || code === 62 ) {
			break;
		}
		i++;
	}
	if ( i >= length ) {
		return null;
	}

	const nameEnd = i;
	const attrs = [];
	let selfClosing = false;

	for (;;) {
		while ( i < length && isSpace( html.charCodeAt( i ) ) ) {
			i++;
		}
		if ( i >= length ) {
			return null;
		}

		let code = html.charCodeAt( i );
		if ( code === 62 ) {
			i++;
			break;
		}
		if ( code === 47 ) {
			if ( html.charCodeAt( i + 1 ) === 62 ) {
				selfClosing = true;
				i += 2;
				break;
			}
			i++;
			continue;
		}

		// An attribute name runs to a space, a slash, a `>` or an `=`, and may itself start with an `=`.
		const start = i;
		i++;
		while ( i < length ) {
			code = html.charCodeAt( i );
			if (
				isSpace( code ) ||
				code === 47 ||
				code === 62 ||
				code === 61
			) {
				break;
			}
			i++;
		}

		const attribute = {
			name: html.slice( start, i ).toLowerCase(),
			start,
			nameEnd: i,
			valueStart: -1,
			valueEnd: -1,
			quote: '',
			end: i,
		};

		let j = i;
		while ( j < length && isSpace( html.charCodeAt( j ) ) ) {
			j++;
		}
		if ( html.charCodeAt( j ) === 61 ) {
			j++;
			while ( j < length && isSpace( html.charCodeAt( j ) ) ) {
				j++;
			}
			if ( j >= length ) {
				return null;
			}

			code = html.charCodeAt( j );
			if ( code === 34 || code === 39 ) {
				const close = html.indexOf( html[ j ], j + 1 );
				if ( close === -1 ) {
					return null;
				}
				attribute.quote = html[ j ];
				attribute.valueStart = j + 1;
				attribute.valueEnd = close;
				attribute.end = close + 1;
			} else {
				attribute.valueStart = j;
				while ( j < length ) {
					code = html.charCodeAt( j );
					if ( isSpace( code ) || code === 62 ) {
						break;
					}
					j++;
				}
				attribute.valueEnd = j;
				attribute.end = j;
			}
			i = attribute.end;
		}

		attrs.push( attribute );
	}

	return {
		type,
		name: html.slice( nameStart, nameEnd ).toLowerCase(),
		start: open,
		nameEnd,
		end: i,
		attrs,
		selfClosing,
		foreign: false,
	};
}

/**
 * Finds where the text content of a script ends: at the first `</script` that is not inside a
 * `<!-- <script> -->` pair, which is the one place a script can hold its own end tag.
 *
 * @param {string} html The HTML.
 * @param {number} from Where the script's content starts.
 *
 * @return {number} The offset of the end tag, or -1 when the script never ends.
 */
function findScriptEnd( html, from ) {
	const pattern = /<!--|-->|<\/?script(?=[\t\n\f\r />])/gi;
	pattern.lastIndex = from;

	// 0: script text. 1: after `<!--`. 2: after `<!--` and `<script`.
	let state = 0;
	let match;
	while ( ( match = pattern.exec( html ) ) ) {
		const token = match[ 0 ].toLowerCase();
		if ( token === '<!--' ) {
			state = state === 0 ? 1 : state;
		} else if ( token === '-->' ) {
			state = 0;
		} else if ( token === '<script' ) {
			state = state === 1 ? 2 : state;
		} else if ( state === 2 ) {
			state = 1;
		} else {
			return match.index;
		}
	}

	return -1;
}

/**
 * Finds where the text content of a raw text element ends.
 *
 * @param {string} html The HTML.
 * @param {string} name The element's name.
 * @param {number} from Where its content starts.
 *
 * @return {number} The offset of the end tag, or -1 when the element never ends.
 */
function findRawTextEnd( html, name, from ) {
	if ( name === 'script' ) {
		return findScriptEnd( html, from );
	}

	const pattern = new RegExp( '</' + name + '(?=[\\t\\n\\f\\r />])', 'gi' );
	pattern.lastIndex = from;
	const match = pattern.exec( html );

	return match ? match.index : -1;
}

/**
 * Finds where a comment that starts at `open` ends.
 *
 * @param {string} html The HTML.
 * @param {number} open Where the comment's `<!--` is.
 *
 * @return {number} The offset just past the comment, or -1 when it never ends.
 */
function findCommentEnd( html, open ) {
	// `<!-->` and `<!--->` are whole, empty comments.
	if ( html.startsWith( '>', open + 4 ) ) {
		return open + 5;
	}
	if ( html.startsWith( '->', open + 4 ) ) {
		return open + 6;
	}

	const pattern = /--!?>/g;
	pattern.lastIndex = open + 4;
	const match = pattern.exec( html );

	return match ? match.index + match[ 0 ].length : -1;
}

/**
 * Whether a MathML start tag is one whose content is parsed as HTML.
 *
 * @param {Tag}    tag  The tag.
 * @param {string} html The HTML.
 *
 * @return {boolean} Whether its content is HTML.
 */
function isMathIntegrationPoint( tag, html ) {
	if ( MATHML_INTEGRATION_POINTS.has( tag.name ) ) {
		return true;
	}
	if ( tag.name !== 'annotation-xml' ) {
		return false;
	}

	const encoding = tag.attrs.find( ( { name } ) => name === 'encoding' );
	const value = encoding
		? html.slice( encoding.valueStart, encoding.valueEnd ).toLowerCase()
		: '';

	return value === 'text/html' || value === 'application/xhtml+xml';
}

/**
 * Returns the start and end tags of a string of HTML, in source order.
 *
 * @param {string} html The HTML.
 *
 * @return {Tag[]} The tags.
 */
export function tokenize( html ) {
	const tags = [];
	const length = html.length;

	// The open SVG and MathML roots, and the elements inside them whose content is HTML again.
	const contexts = [];
	const top = () => contexts[ contexts.length - 1 ];

	let i = 0;
	while ( i < length ) {
		const open = html.indexOf( '<', i );
		if ( open === -1 ) {
			break;
		}

		const next = html.charCodeAt( open + 1 );

		if ( isAlpha( next ) ) {
			const tag = readTag( html, 'start', open, open + 1 );
			if ( ! tag ) {
				break;
			}

			let context = top();
			if ( context?.foreign && FOREIGN_BREAKOUTS.has( tag.name ) ) {
				while ( top()?.foreign ) {
					contexts.pop();
				}
				context = top();
			}

			const isForeignRoot = tag.name === 'svg' || tag.name === 'math';
			tag.foreign = !! context?.foreign || isForeignRoot;
			tags.push( tag );
			i = tag.end;

			if ( context?.foreign ) {
				if ( tag.selfClosing ) {
					continue;
				}
				if ( tag.name === context.name ) {
					context.depth++;
				} else if (
					context.root === 'svg'
						? SVG_INTEGRATION_POINTS.has( tag.name )
						: isMathIntegrationPoint( tag, html )
				) {
					contexts.push( {
						name: tag.name,
						foreign: false,
						root: null,
						depth: 0,
					} );
				}
				continue;
			}

			if ( isForeignRoot ) {
				if ( ! tag.selfClosing ) {
					contexts.push( {
						name: tag.name,
						foreign: true,
						root: tag.name,
						depth: 0,
					} );
				}
				continue;
			}

			if ( RAW_TEXT_ELEMENTS.has( tag.name ) ) {
				const close =
					tag.name === 'plaintext'
						? -1
						: findRawTextEnd( html, tag.name, i );
				if ( close === -1 ) {
					break;
				}

				const endTag = readTag( html, 'end', close, close + 2 );
				if ( ! endTag ) {
					break;
				}
				tags.push( endTag );
				i = endTag.end;
				continue;
			}

			if ( context && tag.name === context.name ) {
				context.depth++;
			}
			continue;
		}

		if ( next === 47 ) {
			const afterSlash = html.charCodeAt( open + 2 );
			if ( isAlpha( afterSlash ) ) {
				const tag = readTag( html, 'end', open, open + 2 );
				if ( ! tag ) {
					break;
				}

				const context = top();
				tag.foreign = !! context?.foreign;
				tags.push( tag );
				i = tag.end;

				if ( context && tag.name === context.name ) {
					if ( context.depth > 0 ) {
						context.depth--;
					} else {
						contexts.pop();
					}
				}
				continue;
			}

			// `</>` is dropped; `</` before anything else opens a comment that runs to the next `>`.
			if ( afterSlash === 62 ) {
				i = open + 3;
				continue;
			}
			const close = html.indexOf( '>', open + 2 );
			if ( close === -1 ) {
				break;
			}
			i = close + 1;
			continue;
		}

		if ( next === 33 ) {
			let end;
			if ( html.startsWith( '--', open + 2 ) ) {
				end = findCommentEnd( html, open );
			} else if (
				top()?.foreign &&
				html.startsWith( '[CDATA[', open + 2 )
			) {
				const close = html.indexOf( ']]>', open + 9 );
				end = close === -1 ? -1 : close + 3;
			} else {
				const close = html.indexOf( '>', open + 2 );
				end = close === -1 ? -1 : close + 1;
			}

			if ( end === -1 ) {
				break;
			}
			i = end;
			continue;
		}

		if ( next === 63 ) {
			const close = html.indexOf( '>', open + 2 );
			if ( close === -1 ) {
				break;
			}
			i = close + 1;
			continue;
		}

		i = open + 1;
	}

	return tags;
}
