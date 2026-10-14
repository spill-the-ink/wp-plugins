<?php
/**
 * Plugin Name:       WordPress Favorites
 * Plugin URI:        https://github.com/spill-the-ink/wp-plugins
 * Description:       Let visitors favorite posts, render their favorites through a Bricks query loop, and toggle favorites with a custom Bricks element.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires           PHP: 7.4
 * Author:            spill-the.ink
 * Author URI:        https://spill-the.ink/?wp-plugins=wp-favorites
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-favorites
 * Domain Path:       /languages
 * Update URI:        https://github.com/spill-the-ink/wp-plugins
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_FAVORITES_VERSION', '0.1.0' );
define( 'WP_FAVORITES_PLUGIN_FILE', __FILE__ );
define( 'WP_FAVORITES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_FAVORITES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WP_FAVORITES_PLUGIN_DIR . 'includes/autoload.php';
require_once WP_FAVORITES_PLUGIN_DIR . 'includes/functions.php';

WpFavorites\Plugin::boot();
