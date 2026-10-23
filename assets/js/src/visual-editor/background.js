/**
 * Finds where a CSS background image is written, in a style attribute or in one of the block's
 * style elements, so the image can be swapped by rewriting that one `url()`.
 */

const isSpace = ( character ) => /\s/.test( character );

/**
 * Reads a CSS string or an unquoted URL up to its end, undoing its escapes.
 *
 * @param {string} css   The CSS.
 * @param {number} from  Where the value starts, past any opening quote.
 * @param {string} quote The quote that ends it, or an empty string for an unquoted URL.
 *
 * @return {{value: string, end: number}|null} The value and the offset of what ends it, or nothing when it never ends.
 */
function readValue( css, from, quote ) {
	let value = '';

	for ( let i = from; i < css.length; i++ ) {
		const character = css[ i ];

		if ( character === '\\' ) {
			const hex = /^[0-9a-f]{1,6}\s?/i.exec( css.slice( i + 1, i + 8 ) );
			if ( hex ) {
				value += String.fromCodePoint( parseInt( hex[ 0 ], 16 ) );
				i += hex[ 0 ].length;
			} else if ( i + 1 < css.length ) {
				value += css[ i + 1 ];
				i++;
			}
			continue;
		}

		if ( quote ? character === quote : character === ')' ) {
			return { value, end: i };
		}
		value += character;
	}

	return null;
}

/**
 * Finds the `url()` tokens in a string of CSS, with the selector of the rule each one is in.
 *
 * @param {string} css A stylesheet, or the value of a style attribute.
 *
 * @return {Array<{start: number, end: number, url: string, quote: string, selector: string|null}>} The tokens, with the offsets of the whole `url(…)`.
 */
export function findUrls( css ) {
	const urls = [];
	const selectors = [];
	let preludeStart = 0;

	for ( let i = 0; i < css.length; i++ ) {
		const character = css[ i ];

		if ( character === '/' && css[ i + 1 ] === '*' ) {
			const close = css.indexOf( '*/', i + 2 );
			if ( close === -1 ) {
				break;
			}
			i = close + 1;
			continue;
		}

		if ( character === '"' || character === "'" ) {
			const string = readValue( css, i + 1, character );
			if ( ! string ) {
				break;
			}
			i = string.end;
			continue;
		}

		if ( character === '{' ) {
			selectors.push(
				css.slice( preludeStart, i ).replace( /\s+/g, ' ' ).trim()
			);
			preludeStart = i + 1;
			continue;
		}
		if ( character === '}' ) {
			selectors.pop();
			preludeStart = i + 1;
			continue;
		}
		if ( character === ';' ) {
			preludeStart = i + 1;
			continue;
		}

		if (
			( character === 'u' || character === 'U' ) &&
			css.slice( i, i + 4 ).toLowerCase() === 'url(' &&
			! /[\w-]/.test( css[ i - 1 ] || '' )
		) {
			let j = i + 4;
			while ( j < css.length && isSpace( css[ j ] ) ) {
				j++;
			}

			const quote = css[ j ] === '"' || css[ j ] === "'" ? css[ j ] : '';
			const read = readValue( css, quote ? j + 1 : j, quote );
			if ( ! read ) {
				break;
			}

			let end = read.end;
			if ( quote ) {
				end++;
				while ( end < css.length && isSpace( css[ end ] ) ) {
					end++;
				}
				if ( css[ end ] !== ')' ) {
					i = end;
					continue;
				}
			}

			urls.push( {
				start: i,
				end: end + 1,
				url: quote ? read.value : read.value.trim(),
				quote,
				selector: selectors.length
					? selectors[ selectors.length - 1 ]
					: null,
			} );
			i = end;
		}
	}

	return urls;
}

/**
 * Writes a `url()` token.
 *
 * @param {string} url   The URL.
 * @param {string} quote The quote to write it in: the one the token it replaces had.
 *
 * @return {string} The token.
 */
export function formatUrl( url, quote ) {
	const mark = quote || ( /[\s"'()\\]/.test( url ) ? '"' : '' );
	const escaped = mark
		? url
				.replace( /\\/g, '\\\\' )
				.split( mark )
				.join( '\\' + mark )
		: url;

	return `url(${ mark }${ escaped }${ mark })`;
}

/**
 * Finds the `url()` that puts a background image on an element.
 *
 * The browser says which image the element shows. That image is then looked for in the element's
 * own style attribute, and after that in the block's style elements, where the rule that names
 * the element is preferred over one that merely uses the same file.
 *
 * @param {Element}                    element The element with the background.
 * @param {Element}                    root    The element holding the block's content.
 * @param {import('./dom-map').DomMap} map     The block's source.
 *
 * @return {{style: Element|null, text: string, token: ReturnType<typeof findUrls>[number]}|null} Where the image is written: in the text of a style element, or in the element's style attribute. Nothing when it is written somewhere this block does not hold.
 */
export function locateBackground( element, root, map ) {
	const doc = element.ownerDocument;
	const resolve = ( url ) => {
		try {
			return new URL( url, doc.baseURI ).href;
		} catch {
			return url;
		}
	};

	const [ shown ] = findUrls(
		doc.defaultView.getComputedStyle( element ).backgroundImage
	);
	if ( ! shown ) {
		return null;
	}
	const wanted = resolve( shown.url );

	const inline = element.getAttribute( 'style' );
	if ( inline ) {
		const token = findUrls( inline ).find(
			( candidate ) => resolve( candidate.url ) === wanted
		);
		if ( token ) {
			return { style: null, text: inline, token };
		}
	}

	const candidates = [];
	for ( const style of root.querySelectorAll( 'style' ) ) {
		const text = map.rawText( style );
		// A browser reads a carriage return and line feed as one line feed, and nothing else differs.
		if (
			text === null ||
			text.replace( /\r\n?/g, '\n' ) !== style.textContent
		) {
			continue;
		}

		for ( const token of findUrls( text ) ) {
			if ( resolve( token.url ) === wanted ) {
				candidates.push( { style, text, token } );
			}
		}
	}

	const named = candidates.filter( ( { token } ) => {
		try {
			return !! token.selector && element.matches( token.selector );
		} catch {
			return false;
		}
	} );

	// Of the rules that name the element, the last one written is the one that applies.
	return named[ named.length - 1 ] || candidates[ 0 ] || null;
}
