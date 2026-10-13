<?php declare( strict_types=1 );
/**
 * The A8CSP AI Canvas bootstrap file.
 *
 * This file must remain parsable on PHP versions below the plugin's declared floor, since it
 * runs before the requirements check can report a friendly error; a dedicated CI job lints it
 * directly against the older PHP versions.
 *
 * @since       1.0.0
 * @version     2.0.0
 * @package     A8C\SpecialProjects\AICanvas
 * @author      A8C Special Projects
 * @license     GPL-2.0-or-later
 *
 * @noinspection    ALL
 *
 * @wordpress-plugin
 * Plugin Name:             A8CSP AI Canvas
 * Plugin URI:              https://specialprojects.automattic.com
 * Update URI:              https://github.com/a8cteam51/a8csp-ai-canvas
 * Description:             Lets Claude Design, or any MCP client, build pages on this site as a single Custom HTML block, and adds the page templates those pages render on.
 * Version:                 2.0.0
 * Requires at least:       7.1
 * Tested up to:            7.1
 * Requires PHP:            8.3
 * Requires Plugins:        mcp-adapter
 * Author:                  A8C Special Projects
 * Author URI:              https://specialprojects.automattic.com
 * License:                 GPL v2 or later
 * License URI:             https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:             a8csp-ai-canvas
 * Domain Path:             /languages
 */

\defined( 'ABSPATH' ) || exit;

// Define plugin constants.
\define( 'A8CSP_AI_CANVAS_FILE', __FILE__ );
\define( 'A8CSP_AI_CANVAS_BASENAME', plugin_basename( __FILE__ ) );
\define( 'A8CSP_AI_CANVAS_DIR_PATH', plugin_dir_path( __FILE__ ) );
\define( 'A8CSP_AI_CANVAS_DIR_URL', plugin_dir_url( __FILE__ ) );

// The bootstrap's helper functions live in functions-bootstrap.php, which shares this file's
// below-floor parse constraint; they must exist before the updater registration and the
// requirements gate below can reference them.
require_once A8CSP_AI_CANVAS_DIR_PATH . 'functions-bootstrap.php';

// The self-updater registers before the requirements gates below: an incompatible install is
// the one that most needs to be offered the corrective update.
add_filter( 'update_plugins_github.com', 'a8csp_ai_canvas_check_github_release_update', 10, 3 );
add_filter( 'plugins_api', 'a8csp_ai_canvas_get_github_release_information', 10, 3 );

// Registration-only since WP 6.7, so include time is safe — and required: core registers the
// header path only for site-active plugins (wp-settings.php skips it in the network-activated
// loop), so network-activated copies lose their bundled translations without this line.
// Gettext calls still wait for `init` (JIT).
load_plugin_textdomain( 'a8csp-ai-canvas', false, dirname( A8CSP_AI_CANVAS_BASENAME ) . '/languages' );

// Load the autoloader.
if ( ! \is_file( A8CSP_AI_CANVAS_DIR_PATH . 'vendor/autoload.php' ) ) {
	a8csp_ai_canvas_output_requirements_error( new WP_Error( 'missing_autoloader' ) );
	return;
}
require_once A8CSP_AI_CANVAS_DIR_PATH . 'vendor/autoload.php';

// Bootstrap the plugin (maybe)!
if ( is_wp_error( a8csp_ai_canvas_validate_requirements() ) ) {
	a8csp_ai_canvas_output_requirements_error( a8csp_ai_canvas_validate_requirements() );
} else {
	require_once A8CSP_AI_CANVAS_DIR_PATH . 'functions.php';
	add_action( 'plugins_loaded', array( a8csp_ai_canvas_plugin(), 'boot' ) );
}
