/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Renders the block's saved markup.
 *
 * The string stays untranslated on purpose: save() output is serialized into
 * post_content and validated byte-for-byte on load, so a locale-dependent
 * string would fail block validation whenever the editor locale differs from
 * the one that saved the post. Translate presentational text in edit.js and on
 * the front end via a dynamic (render_callback) block instead.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @return {Element} Element to render.
 */
export default function save() {
	return (
		<p { ...useBlockProps.save() }>
			An example notice from A8CSP AI Canvas WebMCP.
		</p>
	);
}
