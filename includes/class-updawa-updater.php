<?php
/**
 * Fetches update data for plugins, themes, and WordPress core,
 * and builds the JSON model returned by the admin panel and REST API.
 *
 * @package WP_Watchdog
 */

defined( 'ABSPATH' ) || exit;

class Updawa_Updater {

	/**
	 * Clears transients and forces a fresh fetch of update data.
	 */
	public function force_refresh() {
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		delete_site_transient( 'update_core' );

		wp_update_plugins();
		wp_update_themes();
		wp_version_check();
	}

	/**
	 * Returns the current update status as an array matching the JSON specification.
	 *
	 * @return array
	 */
	public function get_status() {
		$data = array(
			'generated_at' => ( new DateTime() )->format( DateTime::ATOM ),
			'wordpress'    => $this->get_core_status(),
			'plugins'      => $this->get_plugins_status(),
			'themes'       => $this->get_themes_status(),
		);

		$ssl_expiry = $this->get_ssl_expiry();
		if ( null !== $ssl_expiry ) {
			$data['ssl_expires_at'] = $ssl_expiry;
		}

		return $data;
	}

	/**
	 * Returns the SSL certificate expiry date for the site, or null if not on HTTPS
	 * or the certificate cannot be retrieved.
	 *
	 * @return string|null ISO 8601 date string, or null.
	 */
	private function get_ssl_expiry() {
		$site_url = get_site_url();
		if ( 0 !== strpos( $site_url, 'https://' ) ) {
			return null;
		}

		if ( ! function_exists( 'openssl_x509_parse' ) ) {
			return null;
		}

		$host    = wp_parse_url( $site_url, PHP_URL_HOST );
		$context = stream_context_create( array(
			'ssl' => array(
				'capture_peer_cert' => true,
				'verify_peer'       => true,
				'verify_peer_name'  => true,
			),
		) );

		$socket = @stream_socket_client(
			'ssl://' . $host . ':443',
			$errno,
			$errstr,
			10,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( ! $socket ) {
			return null;
		}

		$params = stream_context_get_params( $socket );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- WP_Filesystem has no SSL socket equivalent; fclose() is the only way to release the stream.
		fclose( $socket );

		$cert_resource = isset( $params['options']['ssl']['peer_certificate'] )
			? $params['options']['ssl']['peer_certificate']
			: null;

		if ( ! $cert_resource ) {
			return null;
		}

		$cert_info = openssl_x509_parse( $cert_resource );
		if ( ! $cert_info || empty( $cert_info['validTo_time_t'] ) ) {
			return null;
		}

		return ( new DateTime( '@' . $cert_info['validTo_time_t'] ) )->format( DateTime::ATOM );
	}

	/**
	 * Returns the update status of WordPress core.
	 *
	 * @return array
	 */
	private function get_core_status() {
		global $wp_version;

		$update           = get_site_transient( 'update_core' );
		$update_available = false;
		$new_version      = null;
		$package_url      = null;

		if ( $update && isset( $update->updates ) && is_array( $update->updates ) ) {
			foreach ( $update->updates as $core_update ) {
				if ( 'upgrade' === $core_update->response ) {
					$update_available = true;
					$new_version      = $core_update->version;
					$package_url      = isset( $core_update->packages->full ) ? $core_update->packages->full : null;
					break;
				}
			}
		}

		return array(
			'current_version'  => $wp_version,
			'update_available' => $update_available,
			'new_version'      => $new_version,
			'package_url'      => $package_url,
		);
	}

	/**
	 * Returns the update status of all installed plugins.
	 *
	 * @return array
	 */
	private function get_plugins_status() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$update_data    = get_site_transient( 'update_plugins' );

		$result = array();

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			// Determine the slug: plugin directory name, or filename without extension for single-file plugins.
			$slug = dirname( $plugin_file );
			if ( '.' === $slug ) {
				$slug = basename( $plugin_file, '.php' );
			}

			$update_available = false;
			$new_version      = null;
			$package_url      = null;

			if ( $update_data && isset( $update_data->response[ $plugin_file ] ) ) {
				$plugin_update = $update_data->response[ $plugin_file ];
				$new_version   = isset( $plugin_update->new_version ) ? $plugin_update->new_version : null;
				$package_url   = isset( $plugin_update->package ) ? $plugin_update->package : null;

				if ( $new_version && version_compare( $plugin_data['Version'], $new_version, '>=' ) ) {
					$update_available = false;
				} else {
					$update_available = true;
				}
			}

			$result[] = array(
				'slug'             => $slug,
				'name'             => $plugin_data['Name'],
				'current_version'  => $plugin_data['Version'],
				'update_available' => $update_available,
				'new_version'      => $new_version,
				'package_url'      => $package_url,
				'active'           => in_array( $plugin_file, $active_plugins, true ),
			);
		}

		return $result;
	}

	/**
	 * Returns the update status of all installed themes.
	 *
	 * @return array
	 */
	private function get_themes_status() {
		$all_themes   = wp_get_themes();
		$active_theme = get_option( 'stylesheet' );
		$update_data  = get_site_transient( 'update_themes' );

		$result = array();

		foreach ( $all_themes as $slug => $theme ) {
			$update_available = false;
			$new_version      = null;
			$package_url      = null;

			if ( $update_data && isset( $update_data->response[ $slug ] ) ) {
				$theme_update = $update_data->response[ $slug ];
				$new_version  = isset( $theme_update['new_version'] ) ? $theme_update['new_version'] : null;
				$package_url  = isset( $theme_update['package'] ) ? $theme_update['package'] : null;

				if ( $new_version && version_compare( $theme->get( 'Version' ), $new_version, '>=' ) ) {
					$update_available = false;
				} else {
					$update_available = true;
				}
			}

			$result[] = array(
				'slug'             => $slug,
				'name'             => $theme->get( 'Name' ),
				'current_version'  => $theme->get( 'Version' ),
				'update_available' => $update_available,
				'new_version'      => $new_version,
				'package_url'      => $package_url,
				'active'           => ( $slug === $active_theme ),
			);
		}

		return $result;
	}
}
