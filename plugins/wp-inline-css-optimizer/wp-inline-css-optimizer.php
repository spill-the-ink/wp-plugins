<?php
/**
 * Plugin Name:       WordPress Inline CSS Optimizer
 * Plugin URI:        https://github.com/spill-the-ink/wp-plugins
 * Description:       Minifies inline CSS in the document head — including Tailwind/Winden compiled output — with transient caching and no external dependencies.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            spill-the.ink
 * Author URI:        https://spill-the.ink/?wp-plugins=wp-inline-css-optimizer
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-inline-css-optimizer
 * Domain Path:       /languages
 * Update URI:        https://github.com/spill-the-ink/wp-plugins
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_INLINE_CSS_OPTIMIZER_VERSION', '0.1.0' );
define( 'WP_INLINE_CSS_OPTIMIZER_FILE', __FILE__ );
define( 'WP_INLINE_CSS_OPTIMIZER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_INLINE_CSS_OPTIMIZER_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_INLINE_CSS_OPTIMIZER_OPTION', 'wp_inline_css_optimizer_settings' );
define( 'WP_INLINE_CSS_OPTIMIZER_VERSION_OPTION', 'wp_inline_css_optimizer_version' );

require_once WP_INLINE_CSS_OPTIMIZER_DIR . 'includes/autoload.php';
require_once WP_INLINE_CSS_OPTIMIZER_DIR . 'includes/helpers.php';

register_activation_hook( __FILE__, 'wp_inline_css_optimizer_activate' );
register_deactivation_hook( __FILE__, 'wp_inline_css_optimizer_deactivate' );

/**
 * Seed default settings and version marker on activation.
 */
function wp_inline_css_optimizer_activate(): void {
	if ( false === get_option( WP_INLINE_CSS_OPTIMIZER_OPTION, false ) ) {
		update_option( WP_INLINE_CSS_OPTIMIZER_OPTION, wp_inline_css_optimizer_default_options() );
	}

	update_option( WP_INLINE_CSS_OPTIMIZER_VERSION_OPTION, WP_INLINE_CSS_OPTIMIZER_VERSION );

	wp_inline_css_optimizer_flush_css_cache();
}

/**
 * Clear the compiled-CSS cache on deactivation.
 */
function wp_inline_css_optimizer_deactivate(): void {
	wp_inline_css_optimizer_flush_css_cache();
}

\WpInlineCssOptimizer\Plugin::boot();
