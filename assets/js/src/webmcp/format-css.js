/**
 * Lays out a page's stylesheet for a person to read in the block's CSS tab: each selector of a
 * rule on a line of its own, one declaration per line, indented two spaces per level of nesting,
 * and a blank line between rules. Two spaces, not a tab: the CSS tab draws a tab eight columns
 * wide, which leaves little room for the declarations.
 *
 * Only the space between tokens changes. Strings, comments, escapes and whatever is inside
 * parentheses, such as a `url()` or a `calc()`, are kept as written, but for runs of white space
 * outside strings and comments, which become one space. A declaration gets a space after its
 * colon, and the last one in a rule a semicolon if it had none.
 */

const INDENT = '  ';
const SPACE = /\s/;

/**
 * Splits a selector list at the commas between selectors, not those inside parentheses,
 * brackets or strings.
 *
 * @param {string} text The selector list.
 *
 * @return {string[]} The selectors, trimmed.
 */
function splitSelectors( text ) {
	const parts = [];
	let depth = 0;
	let quote = '';
	let start = 0;

	for ( let i = 0; i < text.length; i++ ) {
		const char = text[ i ];
		if ( quote ) {
			if ( char === '\\' ) {
				i++;
			} else if ( char === quote ) {
				quote = '';
			}
		} else if ( char === '\\' ) {
			i++;
		} else if ( char === '"' || char === "'" ) {
			quote = char;
		} else if ( char === '(' || char === '[' ) {
			depth++;
		} else if ( char === ')' || char === ']' ) {
			depth = Math.max( 0, depth - 1 );
		} else if ( char === ',' && depth === 0 ) {
			parts.push( text.slice( start, i ).trim() );
			start = i + 1;
		}
	}
	parts.push( text.slice( start ).trim() );

	return parts;
}

/**
 * Lays out a stylesheet.
 *
 * @param {string} css The stylesheet.
 *
 * @return {string} The same stylesheet, laid out.
 */
export function formatCss( css ) {
	const lines = [];
	let depth = 0;
	let parens = 0;
	// Braces inside a custom property's value, such as `--x: { a: b }`, are part of the value.
	let valueBraces = 0;
	let buffer = '';
	// Where the declaration's colon is in the buffer, or -1.
	let colon = -1;
	let pendingBlank = false;

	const push = ( text ) => {
		if ( pendingBlank ) {
			lines.push( '' );
			pendingBlank = false;
		}
		lines.push( INDENT.repeat( depth ) + text );
	};

	const take = () => {
		const text = buffer;
		const at = colon;
		buffer = '';
		colon = -1;
		return { text, at };
	};

	const flushStatement = () => {
		const { text, at } = take();
		const trimmed = text.trim();
		if ( ! trimmed ) {
			return;
		}

		if ( depth > 0 && at > 0 && ! trimmed.startsWith( '@' ) ) {
			push(
				`${ text.slice( 0, at ).trim() }: ${ text
					.slice( at + 1 )
					.trim() };`
			);
		} else {
			push( `${ trimmed };` );
		}
	};

	const append = ( text ) => {
		buffer += text;
	};

	const appendSpace = () => {
		if ( buffer && ! buffer.endsWith( ' ' ) ) {
			buffer += ' ';
		}
	};

	for ( let i = 0; i < css.length; i++ ) {
		const char = css[ i ];

		// A comment: on a line of its own between statements, or kept where it is inside one.
		if ( char === '/' && css[ i + 1 ] === '*' ) {
			const end = css.indexOf( '*/', i + 2 );
			const stop = end === -1 ? css.length : end + 2;
			const comment = css.slice( i, stop );
			if ( buffer.trim() ) {
				append( comment );
			} else {
				buffer = '';
				push( comment );
			}
			i = stop - 1;
			continue;
		}

		if ( char === '"' || char === "'" ) {
			let end = i + 1;
			while ( end < css.length && css[ end ] !== char ) {
				if ( css[ end ] === '\\' ) {
					end++;
				} else if ( css[ end ] === '\n' ) {
					break;
				}
				end++;
			}
			append( css.slice( i, end + 1 ) );
			i = end;
			continue;
		}

		if ( char === '\\' ) {
			append( css.slice( i, i + 2 ) );
			i++;
			continue;
		}

		if ( SPACE.test( char ) ) {
			appendSpace();
			continue;
		}

		if ( char === '(' ) {
			parens++;
			append( char );
			continue;
		}
		if ( char === ')' ) {
			parens = Math.max( 0, parens - 1 );
			append( char );
			continue;
		}
		if ( parens > 0 ) {
			append( char );
			continue;
		}

		if ( char === ':' && colon === -1 ) {
			colon = buffer.length;
			append( char );
			continue;
		}

		if ( char === '{' ) {
			if (
				valueBraces > 0 ||
				( colon > 0 && buffer.trim().startsWith( '--' ) )
			) {
				valueBraces++;
				append( char );
				continue;
			}

			const { text } = take();
			const prelude = text.trim();
			if ( prelude.startsWith( '@' ) || ! prelude ) {
				push( `${ prelude } {`.trim() );
			} else {
				const selectors = splitSelectors( prelude );
				selectors.forEach( ( selector, index ) =>
					push(
						index === selectors.length - 1
							? `${ selector } {`
							: `${ selector },`
					)
				);
			}
			depth++;
			continue;
		}

		if ( char === '}' ) {
			if ( valueBraces > 0 ) {
				valueBraces--;
				append( char );
				continue;
			}

			flushStatement();
			depth = Math.max( 0, depth - 1 );
			pendingBlank = false;
			push( '}' );
			pendingBlank = true;
			continue;
		}

		if ( char === ';' && valueBraces === 0 ) {
			flushStatement();
			continue;
		}

		append( char );
	}

	// Whatever was left unfinished is kept, on a line of its own.
	if ( buffer.trim() ) {
		push( buffer.trim() );
	}

	return lines.join( '\n' );
}
