import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';

import { VisualEditor } from './editor';

/**
 * Adds the visual editor to the Custom HTML block. The block's own edit component is left as it
 * is, with its code editor: the visual editor works on what that component renders.
 */
const withVisualEditor = createHigherOrderComponent(
	( BlockEdit ) => ( props ) =>
		'core/html' === props.name ? (
			<>
				<BlockEdit { ...props } />
				<VisualEditor
					clientId={ props.clientId }
					isSelected={ props.isSelected }
				/>
			</>
		) : (
			<BlockEdit { ...props } />
		),
	'withVisualEditor'
);

addFilter(
	'editor.BlockEdit',
	'a8csp-ai-canvas/html-block-visual-editor',
	withVisualEditor
);
