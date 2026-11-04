import {
	LinkControl,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	Button,
	FocalPointPicker,
	MenuGroup,
	MenuItem,
	Popover,
	TextareaControl,
	Toolbar,
	ToolbarButton,
	ToolbarDropdownMenu,
	ToolbarGroup,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	formatBold,
	formatItalic,
	headingLevel1,
	headingLevel2,
	headingLevel3,
	headingLevel4,
	headingLevel5,
	headingLevel6,
	link as linkIcon,
	linkOff,
} from '@wordpress/icons';
import { displayShortcut } from '@wordpress/keycodes';
import {
	filterURLForDisplay,
	isEmail,
	prependHTTP,
	prependHTTPS,
} from '@wordpress/url';

import { LEVELS } from './heading-level';

/**
 * Where the toolbar and its panels go: above what is selected, or below it when there is no room
 * above. A panel takes the toolbar's place while it is open, so only one of them is ever over the
 * page.
 */
const PLACEMENT = {
	placement: 'top-start',
	offset: 14,
	flip: true,
	shift: true,
	resize: false,
	animate: false,
};

/**
 * Turns what was typed into the link field into something a browser can follow: a bare domain
 * gets a scheme, and an email address becomes a `mailto:` link. Paths, anchors and whole URLs
 * stay as typed.
 *
 * @param {string} url What was typed or picked.
 *
 * @return {string} The URL to link to.
 */
function toHref( url ) {
	const trimmed = ( url || '' ).trim();
	if ( /^[a-z][a-z0-9+.-]*:/i.test( trimmed ) ) {
		return trimmed;
	}
	if ( isEmail( trimmed ) ) {
		return 'mailto:' + trimmed;
	}

	return ( prependHTTPS || prependHTTP )( trimmed );
}

/**
 * The link controls: where the link goes, and whether it opens in a new tab. The field searches
 * the site's own pages and posts as well as taking a URL.
 *
 * @param {Object}     props
 * @param {Object}     props.selection What is selected.
 * @param {Object}     props.surface   The block's content.
 * @param {() => void} props.onClose   Closes the controls.
 *
 * @return {Element} The controls.
 */
function LinkPanel( { selection, surface, onClose } ) {
	const { link } = selection;

	return (
		<Popover
			className="a8csp-ve-panel"
			{ ...PLACEMENT }
			anchor={ selection.anchor || selection.element }
			focusOnMount="firstElement"
			onClose={ onClose }
		>
			<LinkControl
				value={
					link
						? { url: link.url, opensInNewTab: link.opensInNewTab }
						: undefined
				}
				forceIsEditingLink
				onChange={ ( next ) => {
					surface.setLink(
						toHref( next.url ),
						!! next.opensInNewTab
					);
					onClose();
				} }
				onCancel={ onClose }
			/>
		</Popover>
	);
}

/**
 * The control for the text that stands in for an image. It saves when it closes, however it is
 * closed: what was typed is never thrown away.
 *
 * @param {Object}     props
 * @param {Object}     props.selection What is selected.
 * @param {Object}     props.surface   The block's content.
 * @param {() => void} props.onClose   Closes the control.
 *
 * @return {Element} The control.
 */
function AltPanel( { selection, surface, onClose } ) {
	const [ alt, setAlt ] = useState( selection.image.alt );
	const [ image ] = useState( selection.element );
	const save = () => {
		surface.setAlt( image, alt.trim() );
		onClose();
	};

	return (
		<Popover
			className="a8csp-ve-panel"
			{ ...PLACEMENT }
			anchor={ selection.anchor || selection.element }
			focusOnMount="firstElement"
			onClose={ save }
		>
			<form
				className="a8csp-ve-panel__form"
				onSubmit={ ( event ) => {
					event.preventDefault();
					save();
				} }
			>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Alternative text', 'a8csp-ai-canvas' ) }
					help={ __(
						'Describe the image for people who cannot see it. Leave this empty if the image is only decoration.',
						'a8csp-ai-canvas'
					) }
					rows={ 3 }
					value={ alt }
					onChange={ setAlt }
				/>
				<Button
					__next40pxDefaultSize
					className="a8csp-ve-panel__done"
					variant="primary"
					type="submit"
				>
					{ __( 'Done', 'a8csp-ai-canvas' ) }
				</Button>
			</form>
		</Popover>
	);
}

/**
 * The control for which part of a background image stays in view when its element crops it. Like
 * the alternative text, it saves when it closes, however it is closed, and only if the point moved.
 *
 * @param {Object}     props
 * @param {Object}     props.selection What is selected.
 * @param {Object}     props.surface   The block's content.
 * @param {() => void} props.onClose   Closes the control.
 *
 * @return {Element} The control.
 */
function FocalPointPanel( { selection, surface, onClose } ) {
	const [ initial ] = useState( selection.background.focalPoint );
	const [ point, setPoint ] = useState( initial );
	const save = () => {
		const next = { x: Number( point.x ), y: Number( point.y ) };
		if ( next.x !== initial.x || next.y !== initial.y ) {
			surface.setFocalPoint( next );
		}
		onClose();
	};

	return (
		<Popover
			className="a8csp-ve-panel"
			{ ...PLACEMENT }
			anchor={ selection.anchor || selection.element }
			focusOnMount="firstElement"
			onClose={ save }
		>
			<form
				className="a8csp-ve-panel__form"
				onSubmit={ ( event ) => {
					event.preventDefault();
					save();
				} }
			>
				<FocalPointPicker
					__nextHasNoMarginBottom
					label={ __( 'Focal point', 'a8csp-ai-canvas' ) }
					help={ __(
						'Choose the part of the image that stays in view when the section crops it.',
						'a8csp-ai-canvas'
					) }
					url={ selection.background.url }
					value={ point }
					onChange={ setPoint }
				/>
				<Button
					__next40pxDefaultSize
					className="a8csp-ve-panel__done"
					variant="primary"
					type="submit"
				>
					{ __( 'Done', 'a8csp-ai-canvas' ) }
				</Button>
			</form>
		</Popover>
	);
}

/**
 * A toolbar button that opens the Media Library to pick an image.
 *
 * @param {Object}                  props
 * @param {string}                  props.label    The button's text.
 * @param {(media: Object) => void} props.onSelect Takes the image that was picked.
 *
 * @return {Element} The button, or nothing for a user who may not use the Media Library.
 */
function ReplaceButton( { label, onSelect } ) {
	return (
		<MediaUploadCheck>
			<MediaUpload
				allowedTypes={ [ 'image' ] }
				onSelect={ onSelect }
				render={ ( { open } ) => (
					<ToolbarButton onClick={ open }>{ label }</ToolbarButton>
				) }
			/>
		</MediaUploadCheck>
	);
}

const LEVEL_ICONS = {
	1: headingLevel1,
	2: headingLevel2,
	3: headingLevel3,
	4: headingLevel4,
	5: headingLevel5,
	6: headingLevel6,
};

/**
 * The heading's level, and the other levels to choose from. A level at which the page's CSS
 * would style the heading differently, because it styles it by its level, says so.
 *
 * @param {Object} props
 * @param {number} props.level   The heading's level.
 * @param {Object} props.surface The block's content.
 *
 * @return {Element} The menu.
 */
function HeadingLevelMenu( { level, surface } ) {
	return (
		<ToolbarDropdownMenu
			icon={ LEVEL_ICONS[ level ] }
			label={ __( 'Change level', 'a8csp-ai-canvas' ) }
		>
			{ ( { onClose } ) => {
				// Worked out when the menu opens, not each time the toolbar is drawn.
				const changing = surface.levelsChangingLook();

				return (
					<MenuGroup>
						{ LEVELS.map( ( option ) => (
							<MenuItem
								key={ option }
								icon={ LEVEL_ICONS[ option ] }
								role="menuitemradio"
								isSelected={ option === level }
								info={
									changing.includes( option )
										? __(
												'Changes how it looks',
												'a8csp-ai-canvas'
											)
										: undefined
								}
								onClick={ () => {
									surface.setHeadingLevel( option );
									onClose();
								} }
							>
								{ sprintf(
									/* translators: %d: A heading level, from 1 to 6. */
									__( 'Heading %d', 'a8csp-ai-canvas' ),
									option
								) }
							</MenuItem>
						) ) }
					</MenuGroup>
				);
			} }
		</ToolbarDropdownMenu>
	);
}

/**
 * The toolbar for what is selected in the block's content, shown beside it: bold, italic and
 * link for text, and the level for a heading; replace, alternative text and link for an image;
 * replace, focal point and remove for a background. Text, an image or a link in front of a
 * background also offers to select the background, which a click on them cannot reach. A link to
 * an anchor on the page offers to go there, which a click on it no longer does.
 *
 * Text that is only being typed in has no toolbar. One appears for a stretch of selected text,
 * for a link or a button, and for text in a heading or in front of a background until the typing
 * starts, which is when there is something for it to do.
 *
 * @param {Object}                   props
 * @param {Object}                   props.selection What is selected.
 * @param {Object}                   props.surface   The block's content.
 * @param {?string}                  props.panel     Which of the toolbar's panels is open: `link`, `alt`, `focal`, or none.
 * @param {(panel: ?string) => void} props.setPanel  Opens a panel, or closes the open one.
 *
 * @return {Element} The toolbar.
 */
export function SelectionToolbar( { selection, surface, panel, setPanel } ) {
	const { kind, link } = selection;

	// Closing a panel hands the keyboard back to the text, where the caret still is.
	const closePanel = () => {
		surface.focus();
		setPanel( null );
	};

	if ( panel === 'link' && ( kind === 'text' || link ) ) {
		return (
			<LinkPanel
				selection={ selection }
				surface={ surface }
				onClose={ closePanel }
			/>
		);
	}
	if ( panel === 'alt' && kind === 'image' ) {
		return (
			<AltPanel
				selection={ selection }
				surface={ surface }
				onClose={ closePanel }
			/>
		);
	}
	if (
		panel === 'focal' &&
		kind === 'background' &&
		selection.background?.canSetFocalPoint
	) {
		return (
			<FocalPointPanel
				selection={ selection }
				surface={ surface }
				onClose={ closePanel }
			/>
		);
	}

	if ( ! selection.anchor ) {
		return null;
	}

	return (
		<Popover
			className="a8csp-ve-toolbar"
			variant="unstyled"
			{ ...PLACEMENT }
			anchor={ selection.anchor }
			focusOnMount={ false }
		>
			{ /* A click on the toolbar must not take the caret out of the text it acts on. */ }
			{ /* eslint-disable-next-line jsx-a11y/no-static-element-interactions */ }
			<div
				className="a8csp-ve-toolbar__frame"
				onMouseDown={ ( event ) => event.preventDefault() }
			>
				<Toolbar
					label={ selection.label }
					className="a8csp-ve-toolbar__bar"
				>
					<div className="a8csp-ve-toolbar__label">
						{ selection.label }
					</div>
					{ selection.headingLevel && (
						<ToolbarGroup>
							<HeadingLevelMenu
								level={ selection.headingLevel }
								surface={ surface }
							/>
						</ToolbarGroup>
					) }
					{ kind === 'text' && (
						<ToolbarGroup>
							<ToolbarButton
								icon={ formatBold }
								label={ __( 'Bold', 'a8csp-ai-canvas' ) }
								shortcut={ displayShortcut.primary( 'b' ) }
								isPressed={ selection.isBold }
								disabled={ ! selection.hasText }
								onClick={ () => surface.toggleFormat( 'bold' ) }
							/>
							<ToolbarButton
								icon={ formatItalic }
								label={ __( 'Italic', 'a8csp-ai-canvas' ) }
								shortcut={ displayShortcut.primary( 'i' ) }
								isPressed={ selection.isItalic }
								disabled={ ! selection.hasText }
								onClick={ () =>
									surface.toggleFormat( 'italic' )
								}
							/>
						</ToolbarGroup>
					) }
					{ kind === 'image' && (
						<ToolbarGroup>
							<ReplaceButton
								label={ __( 'Replace', 'a8csp-ai-canvas' ) }
								onSelect={ ( media ) =>
									surface.replaceImage( media )
								}
							/>
							<ToolbarButton onClick={ () => setPanel( 'alt' ) }>
								{ __( 'Alt text', 'a8csp-ai-canvas' ) }
							</ToolbarButton>
						</ToolbarGroup>
					) }
					{ kind === 'background' && (
						<ToolbarGroup>
							<ReplaceButton
								label={ __( 'Replace', 'a8csp-ai-canvas' ) }
								onSelect={ ( media ) =>
									surface.replaceBackground( media )
								}
							/>
							<ToolbarButton
								disabled={
									! selection.background?.canSetFocalPoint
								}
								onClick={ () => setPanel( 'focal' ) }
							>
								{ __( 'Focal point', 'a8csp-ai-canvas' ) }
							</ToolbarButton>
							<ToolbarButton
								disabled={ ! selection.background?.canRemove }
								onClick={ () => surface.removeBackground() }
							>
								{ __( 'Remove', 'a8csp-ai-canvas' ) }
							</ToolbarButton>
						</ToolbarGroup>
					) }
					{ ( kind === 'text' || link ) && (
						<ToolbarGroup>
							<ToolbarButton
								icon={ linkIcon }
								label={
									link
										? __( 'Change link', 'a8csp-ai-canvas' )
										: __( 'Add link', 'a8csp-ai-canvas' )
								}
								shortcut={
									kind === 'text'
										? displayShortcut.primary( 'k' )
										: undefined
								}
								isPressed={ !! link }
								disabled={ ! link && ! selection.hasText }
								onClick={ () => setPanel( 'link' ) }
							>
								{ link && (
									<span className="a8csp-ve-toolbar__url">
										{ filterURLForDisplay( link.url, 28 ) ||
											__(
												'No address',
												'a8csp-ai-canvas'
											) }
									</span>
								) }
							</ToolbarButton>
							{ link?.isInline && (
								<ToolbarButton
									icon={ linkOff }
									label={ __(
										'Remove link',
										'a8csp-ai-canvas'
									) }
									onClick={ () => surface.removeLink() }
								/>
							) }
							{ link?.hasTarget && (
								<ToolbarButton
									onClick={ () => surface.followLink() }
								>
									{ __( 'Go to anchor', 'a8csp-ai-canvas' ) }
								</ToolbarButton>
							) }
						</ToolbarGroup>
					) }
					{ selection.hasBackground && (
						<ToolbarGroup>
							<ToolbarButton
								onClick={ () => surface.selectBackground() }
							>
								{ __( 'Background', 'a8csp-ai-canvas' ) }
							</ToolbarButton>
						</ToolbarGroup>
					) }
				</Toolbar>
			</div>
		</Popover>
	);
}
