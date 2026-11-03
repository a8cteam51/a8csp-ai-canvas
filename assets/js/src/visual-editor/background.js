/**
 * Finds where a CSS background image is written, in a style attribute or in one of the block's
 * style elements, so the image can be swapped, moved or removed by rewriting that one `url()` or
 * the declarations beside it.
 *
 * A background may belong to an element or to its `::before` or `::after`: a pseudo-element is
 * named with the element that generates it.
 */

const isSpace = ( character ) => /\s/.test( character );

/**
 * A pseudo-element at the end of a selector.
 */
const PSEUDO_ELEMENT = /::?(before|after)\s*$/i;

/**
 * Splits a comma-separated list, a selector list or a list of background layers, at the commas
 * that are not inside brackets or strings.
 *
 * @param {string} list The list.
 *
 * @return {string[]} Its items, trimmed.
 */
export function splitList( list ) {
	const items = [];
	let depth = 0;
	let quote = '';
	let start = 0;

	for ( let i = 0; i < list.length; i++ ) {
		const character = list[ i ];
		if ( quote ) {
			if ( character === '\\' ) {
				i++;
			} else if ( character === quote ) {
				quote = '';
			}
			continue;
		}

		if ( character === '"' || character === "'" ) {
			quote = character;
		} else if ( character === '(' || character === '[' ) {
			depth++;
		} else if ( character === ')' || character === ']' ) {
			depth = Math.max( 0, depth - 1 );
		} else if ( character === ',' && ! depth ) {
			items.push( list.slice( start, i ).trim() );
			start = i + 1;
		}
	}
	items.push( list.slice( start ).trim() );

	return items.filter( Boolean );
}

/**
 * Returns the computed style of an element, or of one of its pseudo-elements.
 *
 * @param {Element}     element The element.
 * @param {string|null} pseudo  `::before`, `::after`, or nothing for the element itself.
 *
 * @return {CSSStyleDeclaration} The style.
 */
function styleOf( element, pseudo ) {
	return element.ownerDocument.defaultView.getComputedStyle(
		element,
		pseudo || null
	);
}

/**
 * Whether an element, or one of its pseudo-elements, shows a background image. A pseudo-element
 * shows one only when it is generated at all, which takes a `content`.
 *
 * @param {Element}     element The element.
 * @param {string|null} pseudo  `::before`, `::after`, or nothing for the element itself.
 *
 * @return {boolean} Whether it does.
 */
export function hasBackgroundImage( element, pseudo = null ) {
	const style = styleOf( element, pseudo );
	if ( pseudo && [ 'none', 'normal', '' ].includes( style.content ) ) {
		return false;
	}

	return /url\(/i.test( style.backgroundImage || '' );
}

/**
 * Whether a rule's selector applies to an element, or to one of its pseudo-elements.
 *
 * @param {string|null} selector The rule's selector.
 * @param {Element}     element  The element.
 * @param {string|null} pseudo   `::before`, `::after`, or nothing for the element itself.
 *
 * @return {boolean} Whether one of the selectors in the list names it.
 */
export function selectorNames( selector, element, pseudo = null ) {
	if ( ! selector ) {
		return false;
	}

	return splitList( selector ).some( ( part ) => {
		const match = PSEUDO_ELEMENT.exec( part );
		const named = match ? '::' + match[ 1 ].toLowerCase() : null;
		if ( named !== ( pseudo || null ) ) {
			return false;
		}

		try {
			return element.matches(
				match ? part.slice( 0, match.index ).trim() || '*' : part
			);
		} catch {
			return false;
		}
	} );
}

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
 * Finds the `url()` tokens in a string of CSS, with the rule each one is in.
 *
 * @param {string} css A stylesheet, or the value of a style attribute.
 *
 * @return {Array<{start: number, end: number, url: string, quote: string, selector: string|null, block: {start: number, end: number}|null}>} The tokens, with the offsets of the whole `url(…)`, and of the body of their rule: from after its `{` to its `}`. A token outside any rule, as in a style attribute, has no selector and no block.
 */
export function findUrls( css ) {
	const urls = [];
	const blocks = [];
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
			blocks.push( {
				selector: css
					.slice( preludeStart, i )
					.replace( /\s+/g, ' ' )
					.trim(),
				start: i + 1,
				end: css.length,
			} );
			preludeStart = i + 1;
			continue;
		}
		if ( character === '}' ) {
			const block = blocks.pop();
			if ( block ) {
				block.end = i;
			}
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

			const block = blocks[ blocks.length - 1 ] || null;
			urls.push( {
				start: i,
				end: end + 1,
				url: quote ? read.value : read.value.trim(),
				quote,
				selector: block ? block.selector : null,
				block,
			} );
			i = end;
		}
	}

	// The blocks were shared while their ends were not yet known: each token gets its own copy.
	return urls.map( ( token ) => ( {
		...token,
		block: token.block && {
			start: token.block.start,
			end: token.block.end,
		},
	} ) );
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
 * A pseudo-element's image is looked for the same way, in the rules that name that pseudo-element;
 * its element's style attribute can hold it only as a custom property the pseudo-element reads.
 *
 * @param {Element}                    element  The element with the background.
 * @param {Element}                    root     The element holding the block's content.
 * @param {import('./dom-map').DomMap} map      The block's source.
 * @param {string|null}                [pseudo] `::before` or `::after` when the background is on one of the element's pseudo-elements.
 *
 * @return {{style: Element|null, text: string, token: ReturnType<typeof findUrls>[number], url: string, named: boolean}|null} Where the image is written: in the text of a style element, or in the element's style attribute; the image's address; and whether the rule or attribute it is written in is the element's own, rather than one it merely shares the file with. Nothing when it is written somewhere this block does not hold.
 */
export function locateBackground( element, root, map, pseudo = null ) {
	const doc = element.ownerDocument;
	const resolve = ( url ) => {
		try {
			return new URL( url, doc.baseURI ).href;
		} catch {
			return url;
		}
	};

	const [ shown ] = findUrls( styleOf( element, pseudo ).backgroundImage );
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
			return {
				style: null,
				text: inline,
				token,
				url: wanted,
				named: ! pseudo,
			};
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

	const named = candidates.filter( ( { token } ) =>
		selectorNames( token.selector, element, pseudo )
	);

	// Of the rules that name the element, the last one written is the one that applies.
	if ( named.length ) {
		return { ...named[ named.length - 1 ], url: wanted, named: true };
	}

	return candidates.length
		? { ...candidates[ 0 ], url: wanted, named: false }
		: null;
}

/**
 * Reads the declarations of a block of CSS: a rule's body or a style attribute. A rule nested in
 * the block is passed over.
 *
 * @param {string} css   The CSS.
 * @param {number} start Where the block starts.
 * @param {number} end   Where it ends.
 *
 * @return {Array<{name: string, start: number, nameStart: number, valueStart: number, valueEnd: number, end: number, hasSemicolon: boolean}>} The declarations, with the offsets of each one, of its name, and of its value without the space around it. `end` is where its semicolon is, or where the block ends.
 */
export function readDeclarations( css, start, end ) {
	const declarations = [];
	let segmentStart = start;
	let colon = -1;
	let depth = 0;
	let braces = 0;
	let quote = '';

	const close = ( at, hasSemicolon ) => {
		if ( colon !== -1 && ! braces ) {
			const name = css
				.slice( segmentStart, colon )
				.replace( /\/\*[\s\S]*?\*\//g, '' )
				.trim()
				.toLowerCase();
			let nameStart = segmentStart;
			while ( nameStart < colon && isSpace( css[ nameStart ] ) ) {
				nameStart++;
			}
			let valueStart = colon + 1;
			while ( valueStart < at && isSpace( css[ valueStart ] ) ) {
				valueStart++;
			}
			let valueEnd = at;
			while ( valueEnd > valueStart && isSpace( css[ valueEnd - 1 ] ) ) {
				valueEnd--;
			}

			if ( name ) {
				declarations.push( {
					name,
					start: segmentStart,
					nameStart,
					valueStart,
					valueEnd,
					end: at,
					hasSemicolon,
				} );
			}
		}

		segmentStart = at + 1;
		colon = -1;
	};

	for ( let i = start; i < end; i++ ) {
		const character = css[ i ];

		if ( quote ) {
			if ( character === '\\' ) {
				i++;
			} else if ( character === quote ) {
				quote = '';
			}
			continue;
		}
		if ( character === '/' && css[ i + 1 ] === '*' ) {
			const commentEnd = css.indexOf( '*/', i + 2 );
			i = commentEnd === -1 ? end : commentEnd + 1;
			continue;
		}

		if ( character === '"' || character === "'" ) {
			quote = character;
		} else if ( character === '(' ) {
			depth++;
		} else if ( character === ')' ) {
			depth = Math.max( 0, depth - 1 );
		} else if ( character === '{' ) {
			braces++;
		} else if ( character === '}' ) {
			braces = Math.max( 0, braces - 1 );
			if ( ! braces ) {
				// A nested rule ends: what follows is a declaration of the block's own.
				segmentStart = i + 1;
				colon = -1;
			}
		} else if ( ! depth && ! braces ) {
			if ( character === ':' && colon === -1 ) {
				colon = i;
			} else if ( character === ';' ) {
				close( i, true );
			}
		}
	}
	close( end, false );

	return declarations;
}

/**
 * Works out the edit that gives a property a value in a block of CSS. The last declaration that
 * decides the property, its own or the shorthand that resets it, is the one that counts: when it
 * is the property's own, its value is replaced; otherwise the declaration is added after the
 * block's last one, laid out as that one is.
 *
 * @param {string}                       css   The CSS.
 * @param {{start: number, end: number}} block The block: a rule's body, or the whole of a style attribute.
 * @param {string}                       name  The property, such as `background-position`.
 * @param {string}                       value Its value.
 *
 * @return {{start: number, end: number, text: string}} The edit: the range of the CSS to replace, and what to put there.
 */
export function setDeclaration( css, block, name, value ) {
	const declarations = readDeclarations( css, block.start, block.end );
	const shorthand = name.split( '-' )[ 0 ];
	const deciding = declarations
		.filter(
			( declaration ) =>
				declaration.name === name || declaration.name === shorthand
		)
		.pop();

	if ( deciding && deciding.name === name ) {
		const isImportant = /!\s*important$/i.test(
			css.slice( deciding.valueStart, deciding.valueEnd )
		);
		return {
			start: deciding.valueStart,
			end: deciding.valueEnd,
			text: value + ( isImportant ? ' !important' : '' ),
		};
	}

	const declaration = `${ name }: ${ value };`;
	const last = declarations[ declarations.length - 1 ];
	if ( ! last ) {
		// An empty rule gets the space a rule has inside its braces; an empty attribute none.
		const isWhole = block.start === 0 && block.end === css.length;
		return {
			start: block.start,
			end: block.end,
			text: isWhole ? declaration : ` ${ declaration } `,
		};
	}

	// A declaration on a line of its own gets a line of its own, indented the same.
	const lead = css.slice( last.start, last.nameStart );
	const lineBreak = lead.lastIndexOf( '\n' );
	const separator = lineBreak === -1 ? ' ' : lead.slice( lineBreak );

	return last.hasSemicolon
		? {
				start: last.end + 1,
				end: last.end + 1,
				text: separator + declaration,
			}
		: {
				start: last.valueEnd,
				end: last.valueEnd,
				text: ';' + separator + declaration,
			};
}

/**
 * Works out the edit that takes a background image away: its `url()` becomes `none`, which is a
 * whole layer of its own, so the declaration stays valid however many layers it has and whatever
 * else it sets. An image inside another function, such as `image-set()`, is not a layer, and is
 * left alone.
 *
 * @param {string}                              css   The CSS the image is written in.
 * @param {ReturnType<typeof findUrls>[number]} token The image's `url()`.
 *
 * @return {{start: number, end: number, text: string}|null} The edit, or nothing when the image cannot be taken out on its own.
 */
export function removalOf( css, token ) {
	const block = token.block || { start: 0, end: css.length };
	const declaration = readDeclarations( css, block.start, block.end ).find(
		( candidate ) =>
			candidate.valueStart <= token.start && token.end <= candidate.end
	);
	if ( ! declaration ) {
		return null;
	}

	let depth = 0;
	let quote = '';
	for ( let i = declaration.valueStart; i < token.start; i++ ) {
		const character = css[ i ];
		if ( quote ) {
			if ( character === '\\' ) {
				i++;
			} else if ( character === quote ) {
				quote = '';
			}
		} else if ( character === '"' || character === "'" ) {
			quote = character;
		} else if ( character === '(' ) {
			depth++;
		} else if ( character === ')' ) {
			depth--;
		}
	}

	return depth ? null : { start: token.start, end: token.end, text: 'none' };
}

/**
 * Returns where an image's focal point is: the `background-position` of its layer, as fractions
 * of the free space across and down. A position that is not in percentages reads as the middle.
 *
 * @param {Element}     element The element.
 * @param {string|null} pseudo  `::before`, `::after`, or nothing for the element itself.
 *
 * @return {{x: number, y: number}} The focal point.
 */
export function focalPointOf( element, pseudo = null ) {
	const style = styleOf( element, pseudo );
	const layers = splitList( style.backgroundImage || '' );
	const layer = Math.max(
		0,
		layers.findIndex( ( image ) => /url\(/i.test( image ) )
	);
	const positions = splitList( style.backgroundPosition || '' );
	const [ x, y ] = ( positions[ layer % ( positions.length || 1 ) ] || '' )
		.trim()
		.split( /\s+/ );
	const read = ( value ) =>
		/^-?\d*\.?\d+%$/.test( value || '' )
			? Math.min( 1, Math.max( 0, parseFloat( value ) / 100 ) )
			: 0.5;

	return { x: read( x ), y: read( y ) };
}

/**
 * Writes a focal point as a `background-position`.
 *
 * @param {{x: number, y: number}} point The focal point, as fractions.
 *
 * @return {string} The position, in percentages to one decimal place.
 */
export function formatPosition( point ) {
	const percent = ( fraction ) =>
		Number(
			( Math.min( 1, Math.max( 0, fraction ) ) * 100 ).toFixed( 1 )
		) + '%';

	return `${ percent( point.x ) } ${ percent( point.y ) }`;
}

/**
 * Applies an edit to a string.
 *
 * @param {string}                                     text The string.
 * @param {{start: number, end: number, text: string}} edit The edit.
 *
 * @return {string} The string with the edit made.
 */
export function applyEdit( text, edit ) {
	return text.slice( 0, edit.start ) + edit.text + text.slice( edit.end );
}
