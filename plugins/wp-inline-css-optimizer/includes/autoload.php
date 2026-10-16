<?php
/**
 * PSR-4 autoloader for the plugin and the copied shared classes.
 *
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( string $qualified_name ): void {
		$map = array(
			'WpInlineCssOptimizer\\' => WP_INLINE_CSS_OPTIMIZER_DIR . 'includes/',
			'WpPluginShared\\'       => WP_INLINE_CSS_OPTIMIZER_DIR . 'includes/shared/',
		);

		foreach ( $map as $prefix => $base ) {
			$length = strlen( $prefix );

			if ( strncmp( $qualified_name, $prefix, $length ) !== 0 ) {
				continue;
			}

			$relative = substr( $qualified_name, $length );
			$file     = $base . str_replace( '\\', '/', $relative ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}
);
