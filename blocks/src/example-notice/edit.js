/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * Renders the block in the editor.
 *
 * @return {Element} Element to render.
 */
export default function Edit() {
	return (
		<p { ...useBlockProps() }>
			{ __(
				'An example notice from A8CSP AI Canvas WebMCP.',
				'a8csp-ai-canvas-webmcp'
			) }
		</p>
	);
}
