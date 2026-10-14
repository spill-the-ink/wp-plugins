<?php

namespace WpFavorites;

use WpFavorites\Bricks\Elements;
use WpFavorites\Bricks\Query;
use WpFavorites\Rest\Rest_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var Assets
	 */
	private $assets;

	/**
	 * @var Rest_Controller
	 */
	private $rest_controller;

	/**
	 * @var Elements
	 */
	private $bricks_elements;

	/**
	 * @var Query
	 */
	private $bricks_query;

	public static function boot(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	public static function instance(): ?self {
		return self::$instance;
	}

	private function __construct() {
		$this->assets          = new Assets();
		$this->rest_controller = new Rest_Controller();
		$this->bricks_elements = new Elements();
		$this->bricks_query    = new Query();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	public function assets(): Assets {
		return $this->assets;
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'wp-favorites', false, dirname( plugin_basename( WP_FAVORITES_PLUGIN_FILE ) ) . '/languages' );
	}
}
