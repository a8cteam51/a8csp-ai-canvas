/**
 * The attributes the visual editor adds to the block's markup while it is open in the editor.
 * None of them is ever saved: they are stripped, or turned back into what they stand for, before
 * anything is written to the block.
 */

/**
 * The prefix every editing attribute carries. A block whose own markup already uses it cannot be
 * told apart from the editor's marks, so the visual editor leaves such a block alone.
 */
export const PREFIX = 'data-a8csp-ve-';

/**
 * Marks each start tag with its position in the source, for as long as it takes to pair the
 * parsed elements with the tags they came from.
 */
export const ATTR_INDEX = PREFIX + 'i';

/**
 * Marks the element whose text is being edited.
 */
export const ATTR_HOST = PREFIX + 'host';

/**
 * Marks the temporary wrapper around a run of text being edited inside an element that also
 * holds things that are not text.
 */
export const ATTR_RUN = PREFIX + 'run';

/**
 * Marks an element whose text was deleted, so it keeps a size to click on.
 */
export const ATTR_EMPTY = PREFIX + 'empty';

/**
 * Marks the line break a browser keeps at the end of the text being edited, so the caret has a
 * line to sit on. It shows nothing, and is not part of the text.
 */
export const ATTR_PAD = PREFIX + 'pad';

/**
 * Marks an image the block's own styles take out of the pointer's way, so the editor leaves it
 * there.
 */
export const ATTR_INERT = PREFIX + 'inert';

/**
 * Elements that never have an end tag.
 */
export const VOID_ELEMENTS = new Set( [
	'area',
	'base',
	'br',
	'col',
	'embed',
	'hr',
	'img',
	'input',
	'link',
	'meta',
	'param',
	'source',
	'track',
	'wbr',
] );

/**
 * Elements whose content the parser reads as text, not as markup.
 */
export const RAW_TEXT_ELEMENTS = new Set( [
	'script',
	'style',
	'textarea',
	'title',
	'xmp',
	'iframe',
	'noembed',
	'noframes',
	'noscript',
	'plaintext',
] );
