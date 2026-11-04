/**
 * Works out how a heading's level bears on its look: which of the page's CSS rules apply to the
 * heading and to what is in it, at each level. A rule written for `h1`, such as `.hero h1 span`,
 * stops applying when the heading becomes an `h2`. One written for `h1, h2, h3` does not.
 */

export const LEVELS = [ 1, 2, 3, 4, 5, 6 ];

// A rule for a pseudo-element counts for the element it belongs to: `matches()` takes no pseudo-element.
const PSEUDO_ELEMENT =
	/::?(?:before|after|first-line|first-letter|marker|placeholder|selection|backdrop)\b/gi;

/**
 * Puts another element in an element's place, with its attributes and content. The attributes
 * are copied as nodes: a name the parser took, such as `@click`, is one `setAttribute()` refuses.
 *
 * @param {Element} element The element.
 * @param {string}  name    The new element's name, lowercased.
 *
 * @return {Element} The new element.
 */
export function swapTag( element, name ) {
	const replacement = element.ownerDocument.createElement( name );
	for ( const attribute of Array.from( element.attributes ) ) {
		replacement.setAttributeNode( attribute.cloneNode() );
	}
	replacement.append( ...element.childNodes );
	element.replaceWith( replacement );

	return replacement;
}

/**
 * Lists the selectors of the style rules in a stylesheet, through `@media`, `@supports` and the
 * like.
 *
 * @param {CSSStyleSheet} sheet The stylesheet.
 *
 * @return {string[]} The selectors.
 */
export function selectorsOf( sheet ) {
	const selectors = [];
	const walk = ( rules ) => {
		for ( const rule of rules ) {
			if ( typeof rule.selectorText === 'string' ) {
				selectors.push(
					rule.selectorText.replace( PSEUDO_ELEMENT, '' )
				);
			} else if ( rule.cssRules ) {
				walk( rule.cssRules );
			}
		}
	};

	try {
		walk( sheet.cssRules );
	} catch {
		// A stylesheet whose rules cannot be read has none to count.
	}

	return selectors;
}

const matches = ( element, selector ) => {
	try {
		return element.matches( selector );
	} catch {
		return false;
	}
};

/**
 * Which selectors match the heading and each element in it.
 *
 * @param {Element}  heading   The heading.
 * @param {string[]} selectors The selectors.
 *
 * @return {string} A record of the matches, equal for two headings the same rules apply to.
 */
function matchesOf( heading, selectors ) {
	return [ heading, ...heading.querySelectorAll( '*' ) ]
		.map( ( element ) =>
			selectors
				.map( ( selector ) => ( matches( element, selector ) ? 1 : 0 ) )
				.join( '' )
		)
		.join( '|' );
}

/**
 * Works out the levels at which a heading would look different, because a different set of the
 * page's rules would apply to it or to what is in it.
 *
 * Each level is tried on a copy of the content in a document of its own, where nothing loads or
 * runs, so the page is not touched.
 *
 * @param {Element}  root      The element holding the block's content.
 * @param {Element}  heading   The heading.
 * @param {string[]} selectors The selectors of the page's style rules.
 *
 * @return {number[]} The levels, other than its own.
 */
export function levelsChangingLook( root, heading, selectors ) {
	if ( ! selectors.length ) {
		return [];
	}

	const path = [];
	for ( let node = heading; node !== root; node = node.parentElement ) {
		path.unshift(
			Array.prototype.indexOf.call( node.parentElement.children, node )
		);
	}

	const scratch = root.ownerDocument.implementation.createHTMLDocument( '' );
	const at = ( level ) => {
		const copy = scratch.importNode( root, true );
		const element = path.reduce( ( node, i ) => node.children[ i ], copy );

		return matchesOf(
			level ? swapTag( element, `h${ level }` ) : element,
			selectors
		);
	};

	const own = at( 0 );
	const current = Number( heading.localName.slice( 1 ) );

	return LEVELS.filter(
		( level ) => level !== current && at( level ) !== own
	);
}
