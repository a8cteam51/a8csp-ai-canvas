/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
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
			An example notice from A8CSP Template Plugin.
		</p>
	);
}
