<?php
/**
 * Self-hosted update checker for WordPress Inline CSS Optimizer.
 *
 * @since 0.1.0
 */

namespace WpInlineCssOptimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Update_Checker {

	const GITHUB_REPO = 'spill-the-ink/wp-plugins';

	/**
	 * Slug prefix used to match this plugin's zip asset on the release.
	 *
	 * @var string
	 */
	const ASSET_SLUG = 'wp-inline-css-optimizer';

	const TRANSIENT_KEY = 'wp_inline_css_optimizer_github_update';
	const PLUGIN_SLUG   = 'wp-inline-css-optimizer/wp-inline-css-optimizer.php';

	public function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_action( 'in_plugin_update_message-' . self::PLUGIN_SLUG, array( $this, 'update_message' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_transient' ), 10, 2 );
	}

	private function github_api_url(): string {
		return 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
	}

	private function github_releases_url(): string {
		return 'https://github.com/' . self::GITHUB_REPO . '/releases';
	}

	private function find_plugin_asset( object $release ): ?array {
		if ( empty( $release->assets ) ) {
			return null;
		}

		foreach ( $release->assets as $asset ) {
			if ( ! isset( $asset->name ) || ! is_string( $asset->name ) ) {
				continue;
			}

			$name = $asset->name;

			if ( 0 !== strpos( $name, self::ASSET_SLUG . '-' ) || ! $this->has_zip_suffix( $name ) ) {
				continue;
			}

			$version = substr( $name, strlen( self::ASSET_SLUG ) + 1, -4 );
			if ( '' === $version ) {
				continue;
			}

			return array(
				'url'     => $asset->browser_download_url ?? '',
				'version' => $version,
			);
		}

		return null;
	}

	private function has_zip_suffix( string $value ): bool {
		return strlen( $value ) >= 4 && 0 === substr_compare( $value, '.zip', -4, 4 );
	}

	public function fetch_github_release(): ?object {
		$cached = get_transient( self::TRANSIENT_KEY );

		if ( false !== $cached ) {
			return ( 'none' === $cached ) ? null : $cached;
		}

		$response = wp_remote_get(
			$this->github_api_url(),
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				),
			),
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::TRANSIENT_KEY, 'none', HOUR_IN_SECONDS );
			return null;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $release ) || ! is_object( $release ) || ! property_exists( $release, 'tag_name' ) ) {
			set_transient( self::TRANSIENT_KEY, 'none', HOUR_IN_SECONDS );
			return null;
		}

		if ( ! empty( $release->prerelease ) || ! empty( $release->draft ) ) {
			set_transient( self::TRANSIENT_KEY, 'none', HOUR_IN_SECONDS );
			return null;
		}

		set_transient( self::TRANSIENT_KEY, $release, 12 * HOUR_IN_SECONDS );

		return $release;
	}

	public function check_for_update( object $transient ): object {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->fetch_github_release();
		if ( null === $release ) {
			return $transient;
		}

		$asset = $this->find_plugin_asset( $release );
		if ( null === $asset ) {
			return $transient;
		}

		$details = (object) array(
			'id'           => self::PLUGIN_SLUG,
			'slug'         => 'wp-inline-css-optimizer',
			'plugin'       => self::PLUGIN_SLUG,
			'new_version'  => $asset['version'],
			'url'          => esc_url( $release->html_url ?? $this->github_releases_url() ),
			'package'      => $asset['url'] ?: false,
			'tested'       => '',
			'requires_php' => '8.0',
		);

		if ( version_compare( $asset['version'], \WP_INLINE_CSS_OPTIMIZER_VERSION, '>' ) ) {
			$transient->response[ self::PLUGIN_SLUG ] = $details;
		} else {
			$transient->no_update[ self::PLUGIN_SLUG ] = $details;
		}

		return $transient;
	}

	public function plugin_info( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || 'wp-inline-css-optimizer' !== $args->slug ) {
			return $result;
		}

		$release = $this->fetch_github_release();
		if ( null === $release ) {
			return $result;
		}

		$asset         = $this->find_plugin_asset( $release );
		$releases_url  = esc_url( $this->github_releases_url() );
		$download_link = ( null !== $asset && $asset['url'] ) ? $asset['url'] : '';

		$changelog = ! empty( $release->body )
			? '<pre>' . esc_html( $release->body ) . '</pre>'
			: '<p>' . esc_html__( 'See the GitHub releases page for the full changelog.', 'wp-inline-css-optimizer' ) . '</p>';

		$info = (object) array(
			'name'          => 'WordPress Inline CSS Optimizer',
			'slug'          => 'wp-inline-css-optimizer',
			'version'       => ( null !== $asset ) ? $asset['version'] : \WP_INLINE_CSS_OPTIMIZER_VERSION,
			'author'        => __( 'WordPress Inline CSS Optimizer', 'wp-inline-css-optimizer' ),
			'homepage'      => $releases_url,
			'requires'      => '6.2',
			'requires_php'  => '8.0',
			'download_link' => $download_link ?: false,
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'Minifies inline CSS in the document head, including Tailwind/Winden compiled output, with transient caching.', 'wp-inline-css-optimizer' ) . '</p>'
					. '<p><a href="' . $releases_url . '" target="_blank">' . esc_html__( 'View all releases on GitHub', 'wp-inline-css-optimizer' ) . '</a></p>',
				'changelog'   => $changelog,
			),
		);

		return $info;
	}

	public function update_message( array $plugin_data, object $response ): void {
		if ( ! empty( $response->package ) ) {
			return;
		}

		$releases_url = esc_url( $this->github_releases_url() );
		printf(
			' <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			$releases_url,
			esc_html__( 'Download from GitHub ↗', 'wp-inline-css-optimizer' )
		);
	}

	public function clear_transient( $upgrader, array $options ): void {
		if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) ) {
			delete_transient( self::TRANSIENT_KEY );
		}
	}
}
