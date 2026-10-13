import {
	Button,
	Flex,
	FlexBlock,
	FlexItem,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import domReady from '@wordpress/dom-ready';
import {
	createInterpolateElement,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

// The ID the plugin registers the connector under; see Connector\Component::CONNECTOR_ID.
const CONNECTOR_ID = 'a8csp-ai-canvas';

/**
 * Encodes text as base64 by way of its UTF-8 bytes, so a non-ASCII username survives.
 *
 * @param {string} text The text to encode.
 *
 * @return {string} The base64 encoding.
 */
const toBase64 = ( text ) =>
	window.btoa(
		String.fromCharCode( ...new window.TextEncoder().encode( text ) )
	);

/**
 * A button that copies a value to the clipboard and says so for a moment.
 *
 * @param {Object} props       Component props.
 * @param {string} props.text  The value to copy.
 * @param {string} props.label The button's accessible label, naming what it copies.
 *
 * @return {Element} The button.
 */
function CopyButton( { text, label } ) {
	const [ isCopied, setIsCopied ] = useState( false );
	const ref = useCopyToClipboard( text, () => setIsCopied( true ) );

	useEffect( () => {
		if ( ! isCopied ) {
			return undefined;
		}

		const timeout = setTimeout( () => setIsCopied( false ), 2000 );
		return () => clearTimeout( timeout );
	}, [ isCopied ] );

	return (
		<Button
			ref={ ref }
			variant="secondary"
			__next40pxDefaultSize
			disabled={ ! text }
			accessibleWhenDisabled
			aria-label={ label }
		>
			{ isCopied
				? __( 'Copied', 'a8csp-ai-canvas' )
				: __( 'Copy', 'a8csp-ai-canvas' ) }
		</Button>
	);
}

/**
 * A read-only value with a button that copies it.
 *
 * @param {Object} props             Component props.
 * @param {string} props.label       The value's label.
 * @param {string} props.value       The value.
 * @param {string} props.copyLabel   The copy button's accessible label.
 * @param {string} props.placeholder What to show while there is no value yet.
 *
 * @return {Element} The row.
 */
function CopyableValue( { label, value, copyLabel, placeholder } ) {
	return (
		<Flex align="flex-end">
			<FlexBlock>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ label }
					value={ value }
					placeholder={ placeholder }
					readOnly
					onChange={ () => {} }
					onFocus={ ( event ) => event.target.select() }
				/>
			</FlexBlock>
			<FlexItem>
				<CopyButton text={ value } label={ copyLabel } />
			</FlexItem>
		</Flex>
	);
}

/**
 * The connection details: who the client connects as, and the two values to give it.
 *
 * The header is computed here, in the browser, so the application password never reaches the site.
 *
 * @return {Element} The fields.
 */
function ConnectionDetails() {
	const { serverUrl, currentUser, users } = window.a8cspAiCanvasConnector;

	const [ login, setLogin ] = useState(
		users.some( ( user ) => user.login === currentUser )
			? currentUser
			: ( users[ 0 ]?.login ?? '' )
	);
	const [ password, setPassword ] = useState( '' );

	const user = users.find( ( candidate ) => candidate.login === login );
	const header =
		login && password.trim()
			? 'Basic ' + toBase64( `${ login }:${ password.trim() }` )
			: '';

	return (
		// `connector-settings` is core's class for a connector's fields: it sets credential inputs in monospace.
		<Flex direction="column" gap={ 4 } className="connector-settings">
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Connect as', 'a8csp-ai-canvas' ) }
				help={ __(
					'The client acts as this user, and can do what this user can do.',
					'a8csp-ai-canvas'
				) }
				value={ login }
				options={ users.map( ( candidate ) => ( {
					value: candidate.login,
					label: sprintf(
						/* translators: 1: A user's display name, 2: Their username */
						__( '%1$s (%2$s)', 'a8csp-ai-canvas' ),
						candidate.name,
						candidate.login
					),
				} ) ) }
				onChange={ setLogin }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Application password', 'a8csp-ai-canvas' ) }
				help={
					<>
						{ user?.passwordsUrl
							? createInterpolateElement(
									__(
										'Create one on <a>this user’s profile</a>.',
										'a8csp-ai-canvas'
									),
									// eslint-disable-next-line jsx-a11y/anchor-has-content -- createInterpolateElement supplies the link text.
									{ a: <a href={ user.passwordsUrl } /> }
								)
							: __(
									'This user, or an administrator who can edit them, creates one on their profile.',
									'a8csp-ai-canvas'
								) }{ ' ' }
						{ __(
							'It is encoded here in your browser, and is not sent to or stored by this site.',
							'a8csp-ai-canvas'
						) }
					</>
				}
				value={ password }
				onChange={ setPassword }
				autoComplete="off"
				autoCapitalize="off"
				spellCheck={ false }
			/>
			<CopyableValue
				label={ __( 'Server URL', 'a8csp-ai-canvas' ) }
				value={ serverUrl }
				copyLabel={ __( 'Copy server URL', 'a8csp-ai-canvas' ) }
			/>
			<CopyableValue
				label={ __( 'Authorization header', 'a8csp-ai-canvas' ) }
				value={ header }
				copyLabel={ __(
					'Copy authorization header',
					'a8csp-ai-canvas'
				) }
				placeholder={ __(
					'Enter an application password to generate it',
					'a8csp-ai-canvas'
				) }
			/>
		</Flex>
	);
}

/**
 * The AI Canvas card on the Connectors screen. Core renders it with the connector's registered
 * name, description, and logo.
 *
 * @param {Object}                        props               Component props.
 * @param {import('react').ComponentType} props.ConnectorItem Core's connector card component.
 * @param {string}                        props.name          The connector's name.
 * @param {string}                        props.description   The connector's description.
 * @param {Element}                       props.logo          The connector's logo.
 *
 * @return {Element} The card.
 */
function ConnectorCard( { ConnectorItem, name, description, logo } ) {
	const [ isExpanded, setIsExpanded ] = useState( false );

	return (
		<ConnectorItem
			className="connector-item--a8csp-ai-canvas"
			logo={ logo }
			name={ name }
			description={ description }
			actionArea={
				<Button
					variant={ isExpanded ? 'tertiary' : 'secondary' }
					size="compact"
					aria-expanded={ isExpanded }
					onClick={ () => setIsExpanded( ! isExpanded ) }
				>
					{ isExpanded
						? __( 'Close', 'a8csp-ai-canvas' )
						: __( 'Set up', 'a8csp-ai-canvas' ) }
				</Button>
			}
		>
			{ isExpanded && <ConnectionDetails /> }
		</ConnectorItem>
	);
}

// Core's Connectors screen is built from script modules, and its registration API is only exported
// from one. WordPress maps the specifier at runtime, so the import is left for the browser, and
// waits for the DOM the way core's own boot does, so the import map has been parsed.
domReady( async () => {
	const {
		__experimentalRegisterConnector: registerConnector,
		__experimentalConnectorItem: ConnectorItem,
	} = await import(
		/* webpackIgnore: true */
		// eslint-disable-next-line import/no-unresolved -- Not an npm package: WordPress serves it and maps the specifier at runtime.
		'@wordpress/connectors'
	);

	registerConnector( CONNECTOR_ID, {
		render: ( props ) => (
			<ConnectorCard { ...props } ConnectorItem={ ConnectorItem } />
		),
	} );
} );
