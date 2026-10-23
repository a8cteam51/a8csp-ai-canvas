/**
 * The boxes drawn over the block's content: one around what the pointer is over, one around what
 * is selected, each with a label saying what it is.
 *
 * The boxes sit in a layer of their own at the end of the document, not inside the content, so
 * nothing about the content's markup or layout changes to show them. Each box follows its target
 * frame by frame, which keeps it in place through scrolling, typing, images loading and anything
 * else that moves the page.
 */
export class Overlay {
	/**
	 * @param {Document} doc The document the block is shown in.
	 */
	constructor( doc ) {
		this.doc = doc;
		this.view = doc.defaultView;

		this.layer = doc.createElement( 'div' );
		this.layer.className = 'a8csp-ve-layer';
		this.layer.setAttribute( 'aria-hidden', 'true' );

		this.boxes = {
			hover: this.createBox( 'is-hover' ),
			selected: this.createBox( 'is-selected' ),
		};

		doc.body.appendChild( this.layer );

		this.frame = 0;
		this.update = this.update.bind( this );
	}

	/**
	 * Creates one box, hidden.
	 *
	 * @param {string} modifier The class that says which box it is.
	 *
	 * @return {Object} The box's elements and state.
	 */
	createBox( modifier ) {
		const element = this.doc.createElement( 'div' );
		element.className = 'a8csp-ve-box ' + modifier;
		element.hidden = true;

		const label = this.doc.createElement( 'span' );
		label.className = 'a8csp-ve-box__label';
		element.appendChild( label );

		this.layer.appendChild( element );

		return { element, label, getRect: null, padding: 0, drawn: '' };
	}

	/**
	 * Shows a box around something, or hides it.
	 *
	 * @param {'hover'|'selected'}     name              Which box.
	 * @param {(function(): DOMRect)=} getRect           Returns where the target is now, relative to the viewport. Leave out to hide the box.
	 * @param {Object}                 [options]
	 * @param {string}                 [options.label]   What the target is.
	 * @param {number}                 [options.padding] The space to leave between the target and the box.
	 */
	set( name, getRect, { label = '', padding = 0 } = {} ) {
		const box = this.boxes[ name ];
		box.getRect = getRect || null;
		box.padding = padding;
		box.drawn = '';
		box.label.textContent = label;
		box.label.hidden = ! label;

		if ( ! box.getRect ) {
			box.element.hidden = true;
		}

		this.update();
	}

	/**
	 * Changes the label of a box that is showing, or takes it away.
	 *
	 * @param {'hover'|'selected'} name  Which box.
	 * @param {string}             label What the target is, or an empty string for no label.
	 */
	setLabel( name, label ) {
		const box = this.boxes[ name ];
		box.label.textContent = label;
		box.label.hidden = ! label;
	}

	/**
	 * Moves each box to where its target is, and comes back next frame while a box is showing.
	 */
	update() {
		this.view.cancelAnimationFrame( this.frame );
		this.frame = 0;

		const origin = this.layer.getBoundingClientRect();
		let isShowing = false;

		for ( const box of Object.values( this.boxes ) ) {
			if ( ! box.getRect ) {
				continue;
			}
			isShowing = true;

			// A target that has since left the page has nowhere to be drawn.
			let rect = null;
			try {
				rect = box.getRect();
			} catch {
				box.getRect = null;
			}
			if ( ! rect || ( ! rect.width && ! rect.height ) ) {
				box.element.hidden = true;
				box.drawn = '';
				continue;
			}

			const left = Math.round( rect.left - origin.left - box.padding );
			const top = Math.round( rect.top - origin.top - box.padding );
			const width = Math.round( rect.width + box.padding * 2 );
			const height = Math.round( rect.height + box.padding * 2 );

			// The label sits above the box, and moves inside it when there is no room above.
			const isTight = rect.top - box.padding < 26;
			const drawn = [ left, top, width, height, isTight ].join();
			if ( drawn === box.drawn ) {
				continue;
			}
			box.drawn = drawn;

			box.element.hidden = false;
			box.element.style.transform = `translate(${ left }px, ${ top }px)`;
			box.element.style.width = width + 'px';
			box.element.style.height = height + 'px';
			box.element.classList.toggle( 'has-label-inside', isTight );
		}

		if ( isShowing ) {
			this.frame = this.view.requestAnimationFrame( this.update );
		}
	}

	/**
	 * Removes the layer.
	 */
	destroy() {
		this.view.cancelAnimationFrame( this.frame );
		this.layer.remove();
	}
}
