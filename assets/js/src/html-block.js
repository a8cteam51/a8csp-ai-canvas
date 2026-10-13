import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';

import './visual-editor';
import '../../css/src/html-block.css';

/**
 * Shows a Custom HTML block that is aligned to the full width across the full width of the
 * editor, in whatever layout the block sits.
 *
 * The editor honours a block's alignment only inside a constrained layout. The plugin's page
 * templates give the content the flow layout, and there the editor leaves the block in the
 * theme's content column, which squeezes a page that fills the viewport on the front end.
 */
const withFullWidth = createHigherOrderComponent(
	( BlockListBlock ) => ( props ) =>
		'core/html' === props.name && 'full' === props.attributes.align ? (
			<BlockListBlock
				{ ...props }
				wrapperProps={ { ...props.wrapperProps, 'data-align': 'full' } }
			/>
		) : (
			<BlockListBlock { ...props } />
		),
	'withFullWidth'
);

addFilter(
	'editor.BlockListBlock',
	'a8csp-ai-canvas/html-block-full-width',
	withFullWidth
);
