/**
 * Picks which file of a Media Library image to use for a spot on the page, and works out the
 * attributes of a link.
 */

/**
 * Reads an image the Media Library handed over, in either of the shapes it comes in: the media
 * modal's, or the REST API's.
 *
 * @param {Object} media The image.
 *
 * @return {{url: string, width: number, height: number, alt: string, sizes: Array<{url: string, width: number, height: number}>}} The image and its generated sizes.
 */
export function readMedia( media ) {
	const details = media.media_details || {};
	const read = ( size ) => ( {
		url: size.url || size.source_url || '',
		width: Number( size.width ) || 0,
		height: Number( size.height ) || 0,
	} );

	return {
		...read( {
			url: media.url || media.source_url,
			width: media.width ?? details.width,
			height: media.height ?? details.height,
		} ),
		alt: media.alt ?? media.alt_text ?? '',
		sizes: Object.values( media.sizes || details.sizes || {} )
			.map( read )
			.filter( ( size ) => size.url && size.width && size.height ),
	};
}

/**
 * Picks the smallest file of an image that still fills a box sharply.
 *
 * Only the sizes with the shape of the original are considered: WordPress also generates
 * cropped ones, a square thumbnail among them, which would show a different picture.
 *
 * @param {ReturnType<typeof readMedia>} image       The image and its sizes.
 * @param {number}                       boxWidth    The width of the box, in CSS pixels.
 * @param {number}                       boxHeight   The height of the box, in CSS pixels.
 * @param {number}                       [density=2] How many device pixels to allow for per CSS pixel.
 *
 * @return {{url: string, width: number, height: number}} The file to use.
 */
export function pickSize( image, boxWidth, boxHeight, density = 2 ) {
	const full = { url: image.url, width: image.width, height: image.height };
	if ( ! full.width || ! full.height ) {
		return full;
	}

	const ratio = full.width / full.height;
	const fits = image.sizes
		.filter(
			( size ) =>
				Math.abs( size.width / size.height - ratio ) / ratio < 0.02 &&
				size.width >= boxWidth * density &&
				size.height >= boxHeight * density
		)
		.sort( ( a, b ) => a.width - b.width );

	return fits[ 0 ] && fits[ 0 ].width < full.width ? fits[ 0 ] : full;
}

/**
 * Works out the `width` and `height` attributes for an image that replaces another.
 *
 * The two attributes tell a browser the picture's shape before it loads, and its size where no
 * style sets one. So the width the page was built with stays, and the height follows the new
 * picture's shape.
 *
 * @param {string|null}                     width  The image's current `width` attribute.
 * @param {string|null}                     height The image's current `height` attribute.
 * @param {{width: number, height: number}} file   The file that replaces it.
 *
 * @return {Object<string, string>} The attributes to set: none when the image had not both.
 */
export function sizeAttributes( width, height, file ) {
	if ( width === null || height === null || ! file.width || ! file.height ) {
		return {};
	}

	const current = parseInt( width, 10 );
	if ( ! ( current > 0 ) || String( current ) !== width.trim() ) {
		return {
			width: String( file.width ),
			height: String( file.height ),
		};
	}

	return {
		height: String(
			Math.max( 1, Math.round( ( current * file.height ) / file.width ) )
		),
	};
}

/**
 * Works out the attributes of a link from where it goes and how it opens.
 *
 * A link that opens in a new tab gets `rel="noopener"`, and loses it when it no longer does.
 * Anything else in `rel` is left alone.
 *
 * @param {string}      url           Where the link goes.
 * @param {boolean}     opensInNewTab Whether it opens in a new tab.
 * @param {string|null} [rel]         The link's current `rel` attribute.
 *
 * @return {{href: string, target: string|null, rel: string|null}} The attributes; null for one to remove.
 */
export function linkAttributes( url, opensInNewTab, rel = null ) {
	const tokens = ( rel || '' )
		.split( /\s+/ )
		.filter( ( token ) => token && token.toLowerCase() !== 'noopener' );
	if ( opensInNewTab ) {
		tokens.push( 'noopener' );
	}

	return {
		href: url,
		target: opensInNewTab ? '_blank' : null,
		rel: tokens.length ? tokens.join( ' ' ) : null,
	};
}
