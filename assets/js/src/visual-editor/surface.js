import { safeHTML } from '@wordpress/dom';
import { __ } from '@wordpress/i18n';

import { formatUrl, locateBackground } from './background';
import {
	ATTR_EMPTY,
	ATTR_HOST,
	ATTR_INERT,
	ATTR_PAD,
	ATTR_RUN,
} from './constants';
import { DomMap } from './dom-map';
import {
	addLink,
	cleanForSave,
	getSelectionOffsets,
	hasFormat,
	linkAt,
	rangeFromOffsets,
	markPadding,
	removeLink,
	removeStrays,
	setSelectionOffsets,
	toggleFormat,
	wasMade,
	wordAt,
} from './formatting';
import { linkAttributes, pickSize, readMedia, sizeAttributes } from './media';
import { Overlay } from './overlay';
import {
	closestLink,
	findRuns,
	isSameTarget,
	labelFor,
	nodesOf,
	rectOf,
	resolveTarget,
} from './targets';

/**
 * How long typing may pause before what was typed is written to the block.
 */
const COMMIT_DELAY = 400;

/**
 * Elements that make whatever holds them more than a line of text.
 */
const BLOCKS =
	'address, article, aside, blockquote, div, dl, figure, footer, form, h1, h2, h3, h4, h5, h6, header, li, main, nav, ol, p, pre, section, table, ul';

/**
 * The space between a target and the box drawn around it.
 */
const PADDING = { text: 4, link: 4, image: 0, background: -4 };

const indexIn = ( list, item ) => Array.prototype.indexOf.call( list, item );

/**
 * The block's content as something to click on and edit.
 *
 * It takes over showing the content from the block editor, so that what is on screen is paired
 * element by element with the block's source, and stays put while the source is rewritten under
 * it. A click then selects what it landed on: text becomes editable where it stands, and an
 * image, a link or a background can be changed from the toolbar the editor shows for it.
 *
 * Every change is written to the block's source at once, to the smallest part of it that holds
 * the change, and handed to the block editor, which owns saving, undo and redo.
 */
export class Surface {
	/**
	 * @param {Object}                 options
	 * @param {Element}                options.block     The block's wrapper element.
	 * @param {Element}                options.container The element the block editor renders the block's content into.
	 * @param {function(): string}     options.getSource Returns the block's HTML as the block editor has it now.
	 * @param {function(string): void} options.onChange  Takes the block's HTML after a change.
	 * @param {function(Object): void} options.onState   Takes what the toolbar needs to know, each time it changes.
	 * @param {function(string): void} options.onNotice  Takes the code of something to tell the user.
	 * @param {function(): void}       options.onLink    Called when the keyboard asks for the link controls.
	 */
	constructor( {
		block,
		container,
		getSource,
		onChange,
		onState,
		onNotice,
		onLink,
	} ) {
		this.block = block;
		this.container = container;
		this.doc = block.ownerDocument;
		this.view = this.doc.defaultView;
		this.getSource = getSource;
		this.onChange = onChange;
		this.onState = onState;
		this.onNotice = onNotice;
		this.onLink = onLink;

		this.map = new DomMap( this.doc );
		this.overlay = new Overlay( this.doc );

		this.root = this.doc.createElement( 'div' );
		this.root.className = 'a8csp-ve-surface';

		this.isActive = false;
		this.html = null;
		this.selection = null;
		this.editing = null;
		this.hovered = null;
		this.pointer = null;
		this.isPointerDown = false;
		this.rangeAnchor = null;
		this.changeable = new WeakMap();
		this.commitTimer = 0;
		this.hoverFrame = 0;
		this.stateFrame = 0;
		this.lastState = '';
		this.hasWarned = false;
		this.listeners = [];

		this.listen();
		this.block.appendChild( this.root );

		// The block editor fills its container again whenever the block changes. While this class shows the content, that copy goes.
		this.observer = new this.view.MutationObserver( () =>
			this.onContainerFilled()
		);
		this.observer.observe( this.container, { childList: true } );

		this.render();
	}

	// region LIFECYCLE

	/**
	 * Attaches the event listeners.
	 */
	listen() {
		const on = ( target, type, handler, options ) => {
			const bound = handler.bind( this );
			target.addEventListener( type, bound, options );
			this.listeners.push( () =>
				target.removeEventListener( type, bound, options )
			);
		};

		on( this.root, 'mousedown', this.onMouseDown, true );
		on( this.root, 'click', this.onClick, true );
		on( this.root, 'submit', this.onSubmit, true );
		on( this.root, 'dragstart', this.onDragStart );
		on( this.root, 'pointermove', this.onPointerMove );
		on( this.root, 'pointerout', this.onPointerOut );
		on( this.root, 'keydown', this.onKeyDown );
		on( this.root, 'keyup', this.onKeyUp );
		on( this.root, 'beforeinput', this.onBeforeInput );
		on( this.root, 'input', this.onInput );
		on( this.root, 'paste', this.onPaste );
		on( this.root, 'copy', this.onCopy );
		on( this.root, 'cut', this.onCopy );
		on( this.root, 'focusout', this.onFocusOut );
		on( this.doc, 'selectionchange', this.onSelectionChange );
		on( this.doc, 'mouseup', this.onMouseUp, true );
		on( this.block, 'mousedown', this.onBlockMouseDown );

		// Typing that is still waiting to be written goes in before the page is left.
		on( this.view, 'pagehide', this.commit );
	}

	/**
	 * Shows the block's current HTML, replacing whatever is shown.
	 */
	render() {
		const memory = this.remember();
		this.release();
		this.html = this.getSource();
		this.changeable = new WeakMap();

		const content = this.html.trim() ? this.map.load( this.html ) : null;
		if ( content ) {
			this.root.replaceChildren( content );
			this.isActive = true;
			this.container.replaceChildren();
			this.markInertImages();

			this.recall( memory );
			this.emit();
		} else {
			this.deactivate();
		}
	}

	/**
	 * Marks the images the block's own styles take out of the pointer's way: a decoration laid
	 * over the content, say, which a click is meant to pass through.
	 *
	 * The block editor switches pointer events off for every image in a block, and the editor's
	 * stylesheet switches them back on here, so an image can be clicked. That must not reach the
	 * images the block itself switched off, and the computed style no longer tells the two apart.
	 * So the block's own rules are read instead.
	 */
	markInertImages() {
		const selectors = [];
		const collect = ( rules ) => {
			for ( const rule of rules || [] ) {
				if (
					rule.selectorText &&
					rule.style?.pointerEvents === 'none'
				) {
					selectors.push( rule.selectorText );
				}
				if ( rule.cssRules?.length ) {
					collect( rule.cssRules );
				}
			}
		};
		for ( const style of this.root.querySelectorAll( 'style' ) ) {
			try {
				collect( style.sheet?.cssRules );
			} catch {
				// A sheet that cannot be read switches nothing off that can be known about.
			}
		}

		for ( const image of this.root.querySelectorAll( 'img' ) ) {
			const isInert =
				image.style.pointerEvents === 'none' ||
				this.view.getComputedStyle( image.parentElement )
					.pointerEvents === 'none' ||
				selectors.some( ( selector ) => {
					try {
						return image.matches( selector );
					} catch {
						return false;
					}
				} );
			if ( isInert ) {
				image.setAttribute( ATTR_INERT, '' );
			}
		}
	}

	/**
	 * Hands showing the content back to the block editor: for a block this class cannot map to
	 * its source, and when the editing ends.
	 */
	deactivate() {
		this.isActive = false;
		this.root.replaceChildren();

		if ( ! this.container.firstChild && this.html && this.html.trim() ) {
			this.container.innerHTML = safeHTML( this.html );
		}

		this.emit();
	}

	/**
	 * Removes the copy of the content the block editor just rendered, unless the block has turned
	 * into something this class does not show, such as HTML with blocks inside it. Then the copy
	 * is the one to keep.
	 */
	onContainerFilled() {
		if ( ! this.isActive || ! this.container.firstChild ) {
			return;
		}

		if ( this.getSource().trim() ) {
			this.container.replaceChildren();
		} else {
			this.release();
			this.html = '';
			this.deactivate();
		}
	}

	/**
	 * Shows the block's HTML again if it is no longer what is shown: after an undo, a redo, or a
	 * change made in the code editor.
	 */
	sync() {
		if ( this.getSource() !== this.html ) {
			this.render();
		}
	}

	/**
	 * Drops the selection and the editing state without writing anything, ahead of the content
	 * being replaced.
	 */
	release() {
		this.view.clearTimeout( this.commitTimer );
		this.commitTimer = 0;
		this.overlay.set( 'hover' );
		this.overlay.set( 'selected' );
		this.selection = null;
		this.editing = null;
		this.hovered = null;
		this.pointer = null;
		this.root.removeAttribute( 'data-cursor' );
	}

	/**
	 * Writes what is pending, hands the content back to the block editor, and removes everything
	 * this class added to the page.
	 */
	destroy() {
		if ( this.block.isConnected ) {
			this.stopEditing();
		}
		this.release();
		this.observer.disconnect();
		this.html = this.getSource();
		this.deactivate();

		this.view.cancelAnimationFrame( this.hoverFrame );
		this.view.cancelAnimationFrame( this.stateFrame );
		this.listeners.forEach( ( remove ) => remove() );
		this.overlay.destroy();
		this.root.remove();
	}

	// endregion

	// region SELECTION

	/**
	 * Selects a target, or nothing. Text that is selected is being edited.
	 *
	 * @param {import('./targets').Target|null} target               What to select.
	 * @param {Object}                          [options]
	 * @param {boolean}                         [options.focusBlock] Whether to move the focus to the block when nothing ends up selected.
	 *
	 * @return {boolean} Whether the target is now selected.
	 */
	select( target, { focusBlock = false } = {} ) {
		if ( target && isSameTarget( target, this.selection?.target ) ) {
			return true;
		}

		this.stopEditing();
		this.selection = null;

		if ( target && this.isActive ) {
			const label = labelFor( target, this.root );
			const selected = this.prepare( target );
			if ( selected ) {
				this.selection = { target: selected, label };
			} else {
				this.onNotice( 'locked' );
			}
		}

		this.hover( null );

		const selected = this.selection?.target;
		this.overlay.set(
			'selected',
			selected && ( () => rectOf( selected ) ),
			{
				label: this.selection?.label,
				padding: selected ? PADDING[ selected.kind ] : 0,
			}
		);

		if ( ! this.selection && focusBlock ) {
			this.block.focus( { preventScroll: true } );
		}

		this.emit();

		return !! this.selection;
	}

	/**
	 * Whether an image, a link or a background is one this class can change. One that is not is
	 * not offered: the pointer shows nothing for it, and a click goes past it.
	 *
	 * A background that cannot be changed is the usual case: one the theme draws, or one whose
	 * image is not written in this block.
	 *
	 * @param {import('./targets').Target} target The target.
	 *
	 * @return {boolean} Whether it can be changed.
	 */
	canChange( target ) {
		const { element } = target;
		if ( ! this.changeable.has( element ) ) {
			this.changeable.set(
				element,
				target.kind === 'background'
					? !! locateBackground( element, this.root, this.map )
					: this.map.hasEditableTag( element )
			);
		}

		return this.changeable.get( element );
	}

	/**
	 * Works out what a click on an element would select, leaving out what cannot be changed.
	 *
	 * @param {Element}                element The element under the pointer.
	 * @param {{x: number, y: number}} point   Where the pointer is.
	 *
	 * @return {import('./targets').Target|null} The target.
	 */
	targetAt( element, point ) {
		const target = resolveTarget( this.root, element, point );

		return target && ( target.kind === 'text' || this.canChange( target ) )
			? target
			: null;
	}

	/**
	 * Checks that a target can be changed, and for text makes it editable.
	 *
	 * A target is only offered when its change can be written to the source: the controls never
	 * show for something they would then fail on.
	 *
	 * @param {import('./targets').Target} target The target.
	 *
	 * @return {import('./targets').Target|null} The target as selected, or nothing when it cannot be changed.
	 */
	prepare( target ) {
		if ( target.kind === 'text' ) {
			const host = this.startEditing( target );
			return host ? { ...target, host, run: null } : null;
		}

		if ( target.kind === 'background' ) {
			if ( ! locateBackground( target.element, this.root, this.map ) ) {
				return null;
			}

			// The toolbar goes where the background was clicked: the element itself may be taller than the screen.
			const rect = target.element.getBoundingClientRect();
			const point = target.point || { x: rect.left, y: rect.top };
			const offset = { x: point.x - rect.left, y: point.y - rect.top };
			return {
				...target,
				anchor: {
					ownerDocument: this.doc,
					contextElement: target.element,
					getBoundingClientRect: () => {
						const now = target.element.getBoundingClientRect();
						return new this.view.DOMRect(
							now.left + offset.x,
							now.top + offset.y,
							0,
							0
						);
					},
				},
			};
		}

		return this.map.hasEditableTag( target.element ) ? target : null;
	}

	/**
	 * Makes a piece of text editable.
	 *
	 * @param {import('./targets').Target} target The text.
	 *
	 * @return {Element|null} The element that is now editable, or nothing when the text cannot be written back to the source.
	 */
	startEditing( target ) {
		// Checked before anything is touched: the check compares the source with the content as it was parsed.
		const writable = this.map.findWritable(
			target.host || target.container,
			this.root
		);
		if ( ! writable ) {
			return null;
		}

		let host = target.host;
		const isRun = ! host;
		if ( isRun ) {
			const nodes = nodesOf( target.run );
			host = this.doc.createElement( 'span' );
			host.setAttribute( ATTR_RUN, '' );
			nodes[ 0 ].before( host );
			host.append( ...nodes );
		}

		host.setAttribute( ATTR_HOST, '' );
		host.setAttribute( 'contenteditable', 'true' );

		const control = host.closest( 'a, button, label, summary' );
		const isInControl = !! control && this.root.contains( control );

		this.editing = {
			host,
			writable,
			isRun,
			offsets: null,
			keepsHardSpaces: host.textContent.includes( '\u00a0' ),
			// A button's label is one line. A card that happens to be a link is not.
			isSingleLine: isInControl && ! control.querySelector( BLOCKS ),
			// A space typed in a button or a summary would press it.
			swallowsSpace: !! host.closest( 'button, summary' ),
		};

		return host;
	}

	/**
	 * Ends the editing of the text that is being edited, and leaves the content as it was found,
	 * but for the change.
	 */
	stopEditing() {
		const editing = this.editing;
		if ( ! editing ) {
			return;
		}

		this.commit();
		if ( this.editing !== editing ) {
			return;
		}
		this.editing = null;

		const { host } = editing;
		const selection = this.doc.getSelection();
		if ( selection && host.contains( selection.anchorNode ) ) {
			selection.removeAllRanges();
		}

		host.removeAttribute( 'contenteditable' );
		host.removeAttribute( ATTR_HOST );

		// What was left out of the saved copy goes from the page too, so the content on screen is again what the source parses to.
		host.querySelectorAll( `[${ ATTR_PAD }]` ).forEach( ( pad ) =>
			pad.remove()
		);
		cleanForSave( host, editing.keepsHardSpaces );

		if ( editing.isRun ) {
			const parent = host.parentNode;
			host.replaceWith( ...host.childNodes );
			parent.normalize();
		} else {
			host.normalize();
			this.markEmpty( host );
		}
	}

	/**
	 * Gives an element with no text left a placeholder to click on, and takes it away again once
	 * there is text.
	 *
	 * @param {Element} host The element.
	 */
	markEmpty( host ) {
		if ( /\S/.test( host.textContent ) ) {
			host.removeAttribute( ATTR_EMPTY );
		} else {
			host.setAttribute(
				ATTR_EMPTY,
				__( 'Type something…', 'a8csp-ai-canvas' )
			);
		}
	}

	/**
	 * Notes what is selected, so it can be selected again once the content has been replaced by
	 * an undo or a redo.
	 *
	 * @return {Object|null} What to select again.
	 */
	remember() {
		if ( ! this.selection || ! this.isActive ) {
			return null;
		}

		const { target } = this.selection;
		const isRun = !! this.editing?.isRun;
		let element = target.element;
		if ( target.kind === 'text' ) {
			element = isRun ? target.container : target.host;
		}
		if ( ! element || ! this.root.contains( element ) ) {
			return null;
		}

		const path = [];
		for (
			let node = element;
			node !== this.root;
			node = node.parentElement
		) {
			path.unshift( indexIn( node.parentElement.children, node ) );
		}

		return {
			kind: target.kind,
			path,
			isRun,
			runIndex: isRun ? indexIn( element.childNodes, target.host ) : -1,
			offsets: this.editing ? this.offsets() : null,
			hadFocus:
				!! this.editing && this.doc.activeElement === this.editing.host,
			point: target.point,
		};
	}

	/**
	 * Selects again what was selected before the content was replaced, if it is still there.
	 *
	 * @param {Object|null} memory What `remember()` returned.
	 */
	recall( memory ) {
		if ( ! memory ) {
			return;
		}

		let element = this.root;
		for ( const index of memory.path ) {
			element = element.children[ index ];
			if ( ! element ) {
				return;
			}
		}

		let target;
		if ( memory.kind === 'background' ) {
			target = { kind: 'background', element, point: memory.point };
		} else if ( memory.isRun ) {
			const node = element.childNodes[ memory.runIndex ];
			const run = findRuns( element ).find( ( candidate ) =>
				nodesOf( candidate ).includes( node )
			);
			if ( ! run ) {
				return;
			}
			target = {
				kind: 'text',
				host: null,
				container: element,
				run,
				frame: null,
				link: closestLink( element, this.root ),
			};
		} else {
			target = resolveTarget( this.root, element );
			if (
				! target ||
				target.kind !== memory.kind ||
				( target.kind === 'text' && target.host !== element )
			) {
				return;
			}
		}

		if ( this.select( target ) && this.editing && memory.offsets ) {
			if ( memory.hadFocus ) {
				this.editing.host.focus( { preventScroll: true } );
			}
			setSelectionOffsets(
				this.editing.host,
				memory.offsets.start,
				memory.offsets.end
			);
		}
	}

	/**
	 * Shows what a click would select.
	 *
	 * @param {import('./targets').Target|null} target What the pointer is over.
	 */
	hover( target ) {
		if ( target && isSameTarget( target, this.selection?.target ) ) {
			target = null;
		}
		if ( target ? isSameTarget( target, this.hovered ) : ! this.hovered ) {
			return;
		}

		this.hovered = target;
		if ( target ) {
			this.root.setAttribute(
				'data-cursor',
				target.kind === 'text' ? 'text' : 'pointer'
			);
		} else {
			this.root.removeAttribute( 'data-cursor' );
		}

		this.overlay.set( 'hover', target && ( () => rectOf( target ) ), {
			label: target ? labelFor( target, this.root ) : '',
			padding: target ? PADDING[ target.kind ] : 0,
		} );
	}

	// endregion

	// region WRITING

	/**
	 * Writes the text being edited to the block's source, and hands the block's HTML on.
	 */
	commit() {
		this.view.clearTimeout( this.commitTimer );
		this.commitTimer = 0;

		const editing = this.editing;
		if ( ! editing || ! this.isActive ) {
			return;
		}

		this.tidy();

		const changed = this.map.write(
			editing.writable,
			editing.keepsHardSpaces ? null : editing.host
		);
		if ( ! changed ) {
			return;
		}

		const isInStep = this.map.reindex( this.root );
		this.push();

		// The source is right either way. If the content no longer lines up with it, it is shown afresh.
		if ( ! isInStep ) {
			this.render();
		}
	}

	/**
	 * Hands the block's HTML on after a change.
	 */
	push() {
		this.html = this.map.html;
		this.onChange( this.html );
	}

	/**
	 * Writes what is typed once the typing pauses.
	 */
	scheduleCommit() {
		this.view.clearTimeout( this.commitTimer );
		this.commitTimer = this.view.setTimeout(
			() => this.commit(),
			COMMIT_DELAY
		);
	}

	/**
	 * Removes what the browser added to the text being edited, and keeps the caret where it was.
	 */
	tidy() {
		const { host } = this.editing;
		const offsets = getSelectionOffsets( host );
		const changed = removeStrays(
			host,
			( element ) => this.map.indexes.has( element ) || wasMade( element )
		);
		if ( changed && offsets ) {
			setSelectionOffsets( host, offsets.start, offsets.end );
		}

		markPadding( host );
		this.markEmpty( host );
	}

	/**
	 * Returns where the selection is in the text being edited, or where it last was.
	 *
	 * @return {{start: number, end: number}|null} The offsets.
	 */
	offsets() {
		const { editing } = this;
		if ( ! editing ) {
			return null;
		}

		const offsets = getSelectionOffsets( editing.host );
		if ( offsets ) {
			editing.offsets = offsets;
		}

		return editing.offsets;
	}

	/**
	 * Returns the stretch of text a command applies to: the selection, or with nothing selected
	 * the word the caret is on.
	 *
	 * @return {{start: number, end: number}|null} The stretch.
	 */
	stretch() {
		const offsets = this.offsets();
		if ( ! offsets ) {
			return null;
		}

		return offsets.start === offsets.end
			? wordAt( this.editing.host, offsets.start )
			: offsets;
	}

	// endregion

	// region COMMANDS

	/**
	 * Makes the selected text bold or italic, or plain again.
	 *
	 * @param {'bold'|'italic'} format The format.
	 */
	toggleFormat( format ) {
		const { editing } = this;
		const stretch = editing && this.stretch();
		if ( ! stretch ) {
			return;
		}

		const offsets = this.offsets();
		if ( toggleFormat( editing.host, stretch, format ) ) {
			this.restoreCaret( offsets || stretch );
			this.commit();
		}
		this.emit();
	}

	/**
	 * Puts the selection back in the text being edited after its elements were rearranged.
	 *
	 * @param {{start: number, end: number}} offsets Where the selection goes.
	 */
	restoreCaret( offsets ) {
		const { host } = this.editing;
		if ( this.doc.activeElement !== host ) {
			host.focus( { preventScroll: true } );
		}
		setSelectionOffsets( host, offsets.start, offsets.end );
		this.editing.offsets = offsets;
	}

	/**
	 * Hands the keyboard back to the content: to the text being edited, with the caret where it
	 * was, or else to the block.
	 */
	focus() {
		if ( ! this.editing ) {
			this.block.focus( { preventScroll: true } );
			return;
		}

		// A click that moved on to other text has already put the caret there.
		const { host } = this.editing;
		if (
			this.doc.activeElement !== host ||
			! getSelectionOffsets( host )
		) {
			this.restoreCaret( this.editing.offsets || { start: 0, end: 0 } );
		}
	}

	/**
	 * Returns the link the selection is in: one inside the text being edited, or the one around
	 * what is selected.
	 *
	 * @return {{element: Element, isInline: boolean}|null} The link, and whether it is a link inside a piece of text, which can be removed, or a button or the like, which cannot.
	 */
	link() {
		if ( ! this.selection ) {
			return null;
		}

		const { target } = this.selection;
		if ( this.editing ) {
			const offsets = this.offsets();
			const inline = offsets && linkAt( this.editing.host, offsets );
			if ( inline ) {
				return { element: inline, isInline: true };
			}
		}

		return target.link && target.link.isConnected
			? { element: target.link, isInline: false }
			: null;
	}

	/**
	 * Points the selected link somewhere else, or turns the selected text into a link.
	 *
	 * @param {string}  url           Where the link goes.
	 * @param {boolean} opensInNewTab Whether it opens in a new tab.
	 */
	setLink( url, opensInNewTab ) {
		const link = this.link();

		if ( link ) {
			this.commit();
			const { element } = link;
			const changes = linkAttributes(
				url,
				opensInNewTab,
				element.getAttribute( 'rel' )
			);
			if ( this.map.setAttributes( element, changes ) ) {
				this.push();
			} else {
				this.onNotice( 'locked' );
			}
		} else if ( this.editing ) {
			const stretch = this.stretch();
			const attributes = Object.fromEntries(
				Object.entries( linkAttributes( url, opensInNewTab ) ).filter(
					( [ , value ] ) => value !== null
				)
			);
			if (
				stretch &&
				addLink( this.editing.host, stretch, attributes )
			) {
				this.restoreCaret( { start: stretch.end, end: stretch.end } );
				this.commit();
			}
		}

		this.emit();
	}

	/**
	 * Removes the link the selection is in, and keeps its text.
	 */
	removeLink() {
		const link = this.link();
		if ( ! link || ! link.isInline || ! this.editing ) {
			return;
		}

		const offsets = this.offsets();
		removeLink( link.element );
		if ( offsets ) {
			this.restoreCaret( offsets );
		}
		this.commit();
		this.emit();
	}

	/**
	 * Replaces the selected image with one from the Media Library.
	 *
	 * @param {Object} media The image, as the Media Library hands it over.
	 */
	replaceImage( media ) {
		const image = this.selection?.target.element;
		if ( this.selection?.target.kind !== 'image' || ! image.isConnected ) {
			return;
		}

		// The sources of a picture would go on showing the old image over the new one.
		const picture = image.closest( 'picture' );
		if ( picture && picture.querySelector( 'source' ) ) {
			if ( ! this.map.isWritable( picture ) ) {
				this.onNotice( 'locked' );
				return;
			}

			picture
				.querySelectorAll( 'source' )
				.forEach( ( source ) => source.remove() );
			if ( this.map.write( picture ) ) {
				const isInStep = this.map.reindex( this.root );
				this.push();
				if ( ! isInStep ) {
					this.render();
					return;
				}
			}
		}

		const read = readMedia( media );
		const box = image.getBoundingClientRect();
		const file = pickSize( read, box.width, box.height );
		const changes = {
			src: file.url,
			srcset: null,
			sizes: null,
			...sizeAttributes(
				image.getAttribute( 'width' ),
				image.getAttribute( 'height' ),
				file
			),
		};
		if ( read.alt ) {
			changes.alt = read.alt;
		}

		if ( file.url && this.map.setAttributes( image, changes ) ) {
			this.push();
		} else {
			this.onNotice( 'locked' );
		}
		this.emit();
	}

	/**
	 * Sets the text that stands in for an image.
	 *
	 * The image is named, not taken from the selection: the control this comes from saves as it
	 * closes, which a click on something else also makes it do.
	 *
	 * @param {Element} image The image.
	 * @param {string}  alt   The text.
	 */
	setAlt( image, alt ) {
		if ( ! image?.isConnected || ! this.root.contains( image ) ) {
			return;
		}
		if ( ( image.getAttribute( 'alt' ) || '' ) === alt ) {
			return;
		}

		if ( this.map.setAttributes( image, { alt } ) ) {
			this.push();
		} else {
			this.onNotice( 'locked' );
		}
		this.emit();
	}

	/**
	 * Replaces the selected background image with one from the Media Library.
	 *
	 * @param {Object} media The image, as the Media Library hands it over.
	 */
	replaceBackground( media ) {
		const element = this.selection?.target.element;
		if (
			this.selection?.target.kind !== 'background' ||
			! element.isConnected
		) {
			return;
		}

		const found = locateBackground( element, this.root, this.map );
		const box = element.getBoundingClientRect();
		const file = pickSize( readMedia( media ), box.width, box.height );
		if ( ! found || ! file.url ) {
			this.onNotice( 'locked' );
			return;
		}

		const { style, text, token } = found;
		const url = formatUrl( file.url, token.quote );
		const isWritten = style
			? this.map.replaceRawText( style, token.start, token.end, url )
			: this.map.setAttributes( element, {
					style:
						text.slice( 0, token.start ) +
						url +
						text.slice( token.end ),
				} );

		if ( isWritten ) {
			this.push();
		} else {
			this.onNotice( 'locked' );
		}
		this.emit();
	}

	// endregion

	// region STATE

	/**
	 * Describes what is selected, for the toolbar.
	 *
	 * @return {Object} The state.
	 */
	describe() {
		if ( ! this.isActive ) {
			return {
				status: this.html && this.html.trim() ? 'unsupported' : 'empty',
				selection: null,
			};
		}
		if ( ! this.selection ) {
			return { status: 'ready', selection: null };
		}

		const { target, label } = this.selection;
		const link = this.link();
		const selection = {
			kind: target.kind,
			label,
			// What is selected, and where its toolbar goes. Text has a toolbar only when it has something to offer.
			element: target.frame || target.host || target.element,
			anchor: target.anchor || target.element || null,
			link: link && {
				url: link.element.getAttribute( 'href' ) || '',
				opensInNewTab:
					link.element.getAttribute( 'target' ) === '_blank',
				isInline: link.isInline,
			},
		};

		if ( this.editing ) {
			const offsets = this.offsets() || { start: 0, end: 0 };
			const { host } = this.editing;
			selection.isBold = hasFormat( host, offsets, 'bold' );
			selection.isItalic = hasFormat( host, offsets, 'italic' );
			selection.hasText = /\S/.test( host.textContent );

			// Bold, italic and link are for a stretch of text, once the mouse has let go of it.
			// The link itself is for a caret inside one. Plain typing gets no toolbar: it would
			// only sit on top of whatever is above the text.
			if ( offsets.start !== offsets.end && ! this.isPointerDown ) {
				selection.anchor = this.anchorOf( host, offsets );
			} else if ( link ) {
				selection.anchor = link.isInline
					? link.element
					: selection.element;
			}
		}
		if ( target.kind === 'image' ) {
			selection.image = {
				src: target.element.currentSrc || target.element.src,
				alt: target.element.getAttribute( 'alt' ) || '',
			};
		}

		return { status: 'ready', selection };
	}

	/**
	 * Returns something for the toolbar to sit beside that stands for a stretch of the text being
	 * edited. It is the same object for as long as the stretch is the same, so the toolbar is not
	 * moved for nothing.
	 *
	 * @param {Element}                      host    The element being edited.
	 * @param {{start: number, end: number}} offsets The stretch.
	 *
	 * @return {Object} What the toolbar is anchored to.
	 */
	anchorOf( host, offsets ) {
		const key = `${ offsets.start }:${ offsets.end }`;
		if ( this.rangeAnchor?.key !== key || this.rangeAnchor.host !== host ) {
			this.rangeAnchor = {
				key,
				host,
				ownerDocument: this.doc,
				contextElement: host,
				getBoundingClientRect: () =>
					rangeFromOffsets(
						host,
						offsets.start,
						offsets.end
					).getBoundingClientRect(),
			};
		}

		return this.rangeAnchor;
	}

	/**
	 * Tells the toolbar what is selected, if that changed.
	 */
	emit() {
		const state = this.describe();
		const { selection } = state;

		// The toolbar names what is selected. Without a toolbar, the box around it does.
		if ( selection ) {
			this.overlay.setLabel(
				'selected',
				selection.anchor ? '' : selection.label
			);
		}

		const key = JSON.stringify( [
			state.status,
			selection && { ...selection, anchor: null, element: null },
		] );
		if (
			key === this.lastState &&
			selection?.anchor === this.lastAnchor &&
			selection?.element === this.lastElement
		) {
			return;
		}

		this.lastState = key;
		this.lastAnchor = selection?.anchor;
		this.lastElement = selection?.element;
		this.onState( state );
	}

	// endregion

	// region EVENTS

	/**
	 * Selects what a click lands on. For text, the element is made editable before the browser
	 * acts on the click, so the caret goes where the pointer is.
	 *
	 * @param {MouseEvent} event The event.
	 */
	onMouseDown( event ) {
		if ( ! this.isActive || event.button !== 0 ) {
			return;
		}

		this.isPointerDown = true;

		const target = this.targetAt( event.target, {
			x: event.clientX,
			y: event.clientY,
		} );

		if ( ! target ) {
			this.select( null );
			return;
		}

		if ( target.kind === 'text' ) {
			if ( ! this.select( target ) ) {
				event.preventDefault();
			}
			return;
		}

		// Nothing to put a caret in: the click must not start a selection or a drag.
		event.preventDefault();
		this.block.focus( { preventScroll: true } );
		this.select( target );
	}

	/**
	 * Notes that the mouse let go, which is when a stretch of text is done being selected.
	 */
	onMouseUp() {
		if ( this.isPointerDown ) {
			this.isPointerDown = false;
			this.emitSoon();
		}
	}

	/**
	 * Says once, on a click, why clicking does nothing in a block this class could not map.
	 */
	onBlockMouseDown() {
		if (
			this.isActive ||
			this.hasWarned ||
			! this.html ||
			! this.html.trim()
		) {
			return;
		}

		this.hasWarned = true;
		this.onNotice( 'unsupported' );
	}

	/**
	 * Keeps a click from doing what it does on the live page: following a link, pressing a
	 * button, ticking a box.
	 *
	 * @param {MouseEvent} event The event.
	 */
	onClick( event ) {
		const summary = event.target.closest( 'summary' );
		if ( summary ) {
			// A closed answer opens, so its text can be reached. An open one stays open while it is edited.
			if ( summary.parentElement.hasAttribute( 'open' ) ) {
				event.preventDefault();
			}
			return;
		}

		if ( event.target.closest( 'a, button, label, input, select, area' ) ) {
			event.preventDefault();
		}
	}

	/**
	 * Keeps a form from being sent.
	 *
	 * @param {Event} event The event.
	 */
	onSubmit( event ) {
		event.preventDefault();
	}

	/**
	 * Keeps images, links and the block itself from being dragged out of the content.
	 *
	 * @param {DragEvent} event The event.
	 */
	onDragStart( event ) {
		event.preventDefault();
		event.stopPropagation();
	}

	/**
	 * Follows the pointer, at most once a frame.
	 *
	 * @param {PointerEvent} event The event.
	 */
	onPointerMove( event ) {
		if ( ! this.isActive || event.buttons ) {
			return;
		}

		this.pointer = {
			element: event.target,
			x: event.clientX,
			y: event.clientY,
		};
		if ( this.hoverFrame ) {
			return;
		}

		this.hoverFrame = this.view.requestAnimationFrame( () => {
			this.hoverFrame = 0;
			if ( this.pointer && this.isActive ) {
				this.hover(
					this.targetAt( this.pointer.element, this.pointer )
				);
			}
		} );
	}

	/**
	 * Stops showing what a click would select once the pointer has left the content.
	 *
	 * @param {PointerEvent} event The event.
	 */
	onPointerOut( event ) {
		if (
			! event.relatedTarget ||
			! this.root.contains( event.relatedTarget )
		) {
			this.pointer = null;
			this.hover( null );
		}
	}

	/**
	 * Handles the keys that mean something while text is edited, and keeps the rest of the
	 * typing from reaching the block editor, which would act on it too.
	 *
	 * @param {KeyboardEvent} event The event.
	 */
	onKeyDown( event ) {
		if ( event.key === 'Escape' && this.selection ) {
			event.preventDefault();
			event.stopPropagation();
			this.select( null, { focusBlock: true } );
			return;
		}

		const { editing } = this;
		if ( ! editing || ! editing.host.contains( event.target ) ) {
			return;
		}

		// A key pressed to compose a character is the input method's, Enter included.
		if ( event.isComposing || event.keyCode === 229 ) {
			event.stopPropagation();
			return;
		}

		if ( ( event.metaKey || event.ctrlKey ) && ! event.altKey ) {
			switch ( event.key.toLowerCase() ) {
				case 'b':
					event.preventDefault();
					event.stopPropagation();
					this.toggleFormat( 'bold' );
					return;
				case 'i':
					event.preventDefault();
					event.stopPropagation();
					this.toggleFormat( 'italic' );
					return;
				case 'k':
					event.preventDefault();
					event.stopPropagation();
					this.onLink();
					return;
				case 'a':
				case 'c':
				case 'v':
				case 'x':
					// The browser's own, kept within the text.
					event.stopPropagation();
					return;
				default:
					// Save, undo and the rest are the editor's. They act on the block as it stands.
					this.commit();
					return;
			}
		}

		if ( event.key === 'Tab' || /^F\d+$/.test( event.key ) ) {
			return;
		}

		event.stopPropagation();

		if ( event.key === 'Enter' ) {
			event.preventDefault();
			if ( editing.isSingleLine ) {
				this.select( null, { focusBlock: true } );
			} else {
				this.insertLineBreak();
			}
		} else if ( event.key === ' ' && editing.swallowsSpace ) {
			event.preventDefault();
			this.doc.execCommand( 'insertText', false, ' ' );
		}
	}

	/**
	 * Keeps a space typed in a button from pressing it when the key comes up.
	 *
	 * @param {KeyboardEvent} event The event.
	 */
	onKeyUp( event ) {
		if ( event.key === ' ' && this.editing?.swallowsSpace ) {
			event.preventDefault();
		}
	}

	/**
	 * Starts a new line in the text being edited.
	 */
	insertLineBreak() {
		if ( this.doc.execCommand( 'insertLineBreak' ) ) {
			return;
		}

		const selection = this.doc.getSelection();
		if ( ! selection || ! selection.rangeCount ) {
			return;
		}

		const range = selection.getRangeAt( 0 );
		range.deleteContents();
		const lineBreak = this.doc.createElement( 'br' );
		range.insertNode( lineBreak );
		range.setStartAfter( lineBreak );
		range.collapse( true );
		selection.removeAllRanges();
		selection.addRange( range );
		this.onInput();
	}

	/**
	 * Refuses the edits a browser offers that have no place here: its own formatting and undo,
	 * new paragraphs, and anything dragged in.
	 *
	 * @param {InputEvent} event The event.
	 */
	onBeforeInput( event ) {
		if ( ! this.editing ) {
			return;
		}

		const type = event.inputType || '';
		if (
			type === 'insertParagraph' ||
			type === 'insertFromDrop' ||
			type === 'deleteByDrag' ||
			type === 'insertOrderedList' ||
			type === 'insertUnorderedList' ||
			type === 'insertHorizontalRule' ||
			type.startsWith( 'format' ) ||
			type.startsWith( 'history' )
		) {
			event.preventDefault();
		}
	}

	/**
	 * Notes that the text changed.
	 *
	 * @param {InputEvent=} event The event.
	 */
	onInput( event ) {
		if ( ! this.editing ) {
			return;
		}

		// The text is left alone while an input method is still composing a character.
		if ( ! event?.isComposing ) {
			this.tidy();
		}
		this.scheduleCommit();
		this.emitSoon();
	}

	/**
	 * Pastes as plain text.
	 *
	 * @param {ClipboardEvent} event The event.
	 */
	onPaste( event ) {
		if ( ! this.editing ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		const text = ( event.clipboardData?.getData( 'text/plain' ) || '' )
			.replace( /\r\n?/g, '\n' )
			.replace( /^\n+|\n+$/g, '' );
		if ( ! text ) {
			return;
		}

		if ( this.editing.isSingleLine ) {
			this.doc.execCommand(
				'insertText',
				false,
				text.replace( /\s*\n\s*/g, ' ' )
			);
			return;
		}

		text.split( '\n' ).forEach( ( line, index ) => {
			if ( index ) {
				this.insertLineBreak();
			}
			if ( line ) {
				this.doc.execCommand( 'insertText', false, line );
			}
		} );
	}

	/**
	 * Leaves copying and cutting text to the browser: the block editor would copy the block.
	 *
	 * @param {ClipboardEvent} event The event.
	 */
	onCopy( event ) {
		if ( this.editing ) {
			event.stopPropagation();
		}
	}

	/**
	 * Writes the text as soon as the focus leaves it, so a click on Save saves what was typed.
	 *
	 * @param {FocusEvent} event The event.
	 */
	onFocusOut( event ) {
		if ( this.editing && event.target === this.editing.host ) {
			this.commit();
		}
	}

	/**
	 * Keeps the toolbar in step with the caret.
	 */
	onSelectionChange() {
		if ( this.editing ) {
			this.emitSoon();
		}
	}

	/**
	 * Tells the toolbar what is selected on the next frame.
	 */
	emitSoon() {
		if ( this.stateFrame ) {
			return;
		}

		this.stateFrame = this.view.requestAnimationFrame( () => {
			this.stateFrame = 0;
			this.emit();
		} );
	}

	// endregion
}
