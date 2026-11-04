/**
 * The content of an AI Canvas page: one Custom HTML block holding a style element, a script
 * element and the page's markup, in that order. The agent sends the three parts, and these
 * functions join them into the block's HTML, split that HTML back up, and check what was sent.
 */

const STYLE = /^\s*<style data-wp-block-html="css">([\s\S]*?)<\/style>/;
const SCRIPT = /^\s*<script data-wp-block-html="js">([\s\S]*?)<\/script>/;

/**
 * Joins a page's CSS, JavaScript and markup into the HTML of its block. The `data-wp-block-html`
 * attributes are how the editor shows the CSS and the JavaScript in their own tabs. An empty
 * style or script element is left out.
 *
 * @param {Object} parts
 * @param {string} parts.css    The stylesheet, without its element.
 * @param {string} parts.js     The script, without its element.
 * @param {string} parts.markup The markup.
 *
 * @return {string} The block's HTML.
 */
export function joinContent( { css = '', js = '', markup = '' } ) {
	const elements = [];

	if ( css.trim() ) {
		elements.push(
			`<style data-wp-block-html="css">\n${ css.trim() }\n</style>`
		);
	}

	if ( js.trim() ) {
		elements.push(
			`<script data-wp-block-html="js">\n${ js.trim() }\n</script>`
		);
	}

	elements.push( markup.trim() );

	return elements.join( '\n\n' );
}

/**
 * Splits the HTML of a page's block into its CSS, JavaScript and markup. Whatever does not open
 * with the block's own style and script elements is markup, so HTML a person wrote differently
 * comes back whole.
 *
 * @param {string} html The block's HTML.
 *
 * @return {{css: string, js: string, markup: string}} The parts.
 */
export function splitContent( html ) {
	let rest = html;
	let css = '';
	let js = '';

	const style = rest.match( STYLE );
	if ( style ) {
		css = style[ 1 ].trim();
		rest = rest.slice( style[ 0 ].length );
	}

	const script = rest.match( SCRIPT );
	if ( script ) {
		js = script[ 1 ].trim();
		rest = rest.slice( script[ 0 ].length );
	}

	return { css, js, markup: rest.trim() };
}

/**
 * Checks the parts an agent sent for what would break the page or its block: a block comment,
 * which would split the block in two, an end tag that would close the style or script element
 * early, a whole document where a fragment belongs, and markup that is not one root element with
 * a class to scope the stylesheet to.
 *
 * @param {Object} parts
 * @param {string} parts.css    The stylesheet, without its element.
 * @param {string} parts.js     The script, without its element.
 * @param {string} parts.markup The markup.
 *
 * @return {{error: string}|{rootClass: string}} What is wrong, or the root element's class.
 */
export function checkContent( { css, js, markup } ) {
	if (
		'string' !== typeof css ||
		'string' !== typeof js ||
		'string' !== typeof markup
	) {
		return { error: '`css`, `js` and `markup` must be strings.' };
	}

	if ( /<!--\s*\/?wp:/.test( css + js + markup ) ) {
		return {
			error: 'Remove the WordPress block comments (<!-- wp:… -->): the tool writes the block itself. Send only the CSS, the JavaScript and the markup.',
		};
	}

	if ( /<\/style/i.test( css ) ) {
		return {
			error: '`css` must not contain </style>. Send the stylesheet without its <style> element.',
		};
	}

	if ( /<\/script/i.test( js ) ) {
		return {
			error: '`js` must not contain </script>. Send the script without its <script> element.',
		};
	}

	if ( /<!doctype|<(html|head|body)[\s>]/i.test( markup ) ) {
		return {
			error: '`markup` must be a fragment, not a document: no <!DOCTYPE>, <html>, <head> or <body>.',
		};
	}

	const template = document.createElement( 'template' );
	template.innerHTML = markup;

	const nodes = Array.from( template.content.childNodes ).filter(
		( node ) =>
			node.nodeType !== node.COMMENT_NODE &&
			! ( node.nodeType === node.TEXT_NODE && ! node.textContent.trim() )
	);
	if (
		1 !== nodes.length ||
		nodes[ 0 ].nodeType !== nodes[ 0 ].ELEMENT_NODE
	) {
		return {
			error: '`markup` must be exactly one root element, with everything else inside it.',
		};
	}

	const rootClass = nodes[ 0 ].classList[ 0 ];
	if ( ! rootClass ) {
		return {
			error: 'The root element of `markup` needs a unique class, named for the page, that every CSS selector starts with.',
		};
	}

	return { rootClass };
}

/**
 * Returns a short fingerprint of a block's HTML, so a write can tell whether the page changed
 * since the agent last read it. It only has to tell versions of one page apart, not resist
 * tampering, so a fast hash does: FNV-1a over the UTF-16 code units. Whitespace around the HTML
 * is left out: a block loaded from the database keeps the line breaks around its HTML and a block
 * just written has none, and the page is the same either way.
 *
 * @param {string} html The block's HTML.
 *
 * @return {string} Eight hexadecimal digits.
 */
export function revisionOf( html ) {
	const text = html.trim();
	let hash = 0x811c9dc5;

	/* eslint-disable no-bitwise -- FNV-1a is defined in 32-bit unsigned arithmetic. */
	for ( let index = 0; index < text.length; index++ ) {
		hash ^= text.charCodeAt( index );
		hash = Math.imul( hash, 0x01000193 ) >>> 0;
	}
	/* eslint-enable no-bitwise */

	return hash.toString( 16 ).padStart( 8, '0' );
}
