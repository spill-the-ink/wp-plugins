<?php
/**
 * Uninstall handler for WordPress Inline CSS Optimizer.
 *
 * @since 0.1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wp_inline_css_optimizer_settings' );
delete_option( 'wp_inline_css_optimizer_version' );
delete_transient( 'wp_inline_css_optimizer_github_update' );

// Remove every cached compiled-CSS transient owned by the plugin.
global $wpdb;

$like_transient = $wpdb->esc_like( '_transient_wp_inline_css_optimizer_css_' ) . '%';
$like_timeout   = $wpdb->esc_like( '_transient_timeout_wp_inline_css_optimizer_css_' ) . '%';

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$like_transient,
		$like_timeout
	)
);
