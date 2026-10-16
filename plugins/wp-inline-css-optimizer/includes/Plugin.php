<?php
/**
 * Plugin bootstrap: wires the optimizer, integrations, updater and settings UI.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer;

use WpInlineCssOptimizer\Debug\Reporter;
use WpInlineCssOptimizer\Optimizer\Cache;
use WpInlineCssOptimizer\Optimizer\Minifier;
use WpInlineCssOptimizer\Optimizer\Optimizer;
use WpInlineCssOptimizer\Integrations\Output_Buffer;
use WpInlineCssOptimizer\Integrations\Winden;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	private static ?Plugin $instance = null;

	private Reporter $reporter;
	private Optimizer $optimizer;
	private Winden $winden;
	private Output_Buffer $buffer;

	private ?Update_Checker $update_checker = null;
	private ?Admin\Settings_Page $settings_page = null;

	/**
	 * Boot the plugin exactly once.
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = new self();
	}

	public static function instance(): ?Plugin {
		return self::$instance;
	}

	private function __construct() {
		$minifier = new Minifier();

		$this->reporter  = new Reporter();
		$cache           = new Cache( $this->reporter );
		$this->optimizer = new Optimizer( $minifier, $cache, $this->reporter );
		$this->winden    = new Winden( $this->optimizer, $minifier, $this->reporter );
		$this->buffer    = new Output_Buffer( $minifier, $cache, $this->reporter );

		$this->optimizer->setup();
		$this->winden->setup();
		$this->buffer->setup();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-inline-css-optimizer',
			false,
			dirname( plugin_basename( WP_INLINE_CSS_OPTIMIZER_FILE ) ) . '/languages'
		);
	}

	public function init(): void {
		$this->reporter->setup();

		$this->update_checker = new Update_Checker();

		if ( is_admin() ) {
			$this->settings_page = new Admin\Settings_Page();
		}
	}
}
