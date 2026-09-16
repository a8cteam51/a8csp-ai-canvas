<?php
/**
 * Plugin Name:     AI-Canvas
 * Plugin URI:      https://github.com/a8cteam51/ai-canvas
 * Update URI:      https://github.com/a8cteam51/ai-canvas
 * Description:     Lets an AI agent build and edit pages and posts as a single full-width Custom HTML block over MCP, with revisions for undo and Media Library access.
 * Author:          Team51
 * Text Domain:     ai-canvas
 * Version:         1.0.1
 * Requires at least: 6.9
 * Requires PHP:    8.1
 * Requires Plugins: mcp-adapter
 *
 * @package         Ai_Canvas
 */

defined( 'ABSPATH' ) || exit;

define( 'AI_CANVAS_VERSION', '1.0.1' );
define( 'AI_CANVAS_FILE', __FILE__ );
define( 'AI_CANVAS_DIR', plugin_dir_path( __FILE__ ) );

// The Abilities API ships in core 6.9+; without it there is no MCP surface.
if ( ! function_exists( 'wp_register_ability' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>AI-Canvas requires WordPress 6.9 or newer (Abilities API).</p></div>';
		}
	);
	return;
}

require AI_CANVAS_DIR . 'includes/class-guidelines.php';
require AI_CANVAS_DIR . 'includes/class-content.php';
require AI_CANVAS_DIR . 'includes/class-render.php';
require AI_CANVAS_DIR . 'includes/class-cache.php';
require AI_CANVAS_DIR . 'includes/class-media.php';
require AI_CANVAS_DIR . 'includes/class-pages.php';
require AI_CANVAS_DIR . 'includes/class-abilities.php';
require AI_CANVAS_DIR . 'includes/class-mcp.php';
require AI_CANVAS_DIR . 'includes/class-compat.php';

AI_Canvas_Render::init();
AI_Canvas_Abilities::init();
AI_Canvas_MCP::init();
AI_Canvas_Compat::init();
