<?php
/**
 * Registers and handles the REST API endpoint /wp-json/update-watchdog/v1/status.
 * Authorization via header: Authorization: Bearer {TOKEN}
 *
 * @package WP_Watchdog
 */

defined( 'ABSPATH' ) || exit;

class Updawa_API {

	/**
	 * @var Updawa_Updater
	 */
	private $updater;

	public function __construct(Updawa_Updater $updater ) {
		$this->updater = $updater;
	}

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'update-watchdog/v1',
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Callback for GET /wp-json/update-watchdog/v1/status permissions
	 *
	 * @param WP_REST_Request $request
	 * @return true|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		// Prevent aggressive caching plugins/proxies from caching this request.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();

		$auth_header = $request->get_header( 'authorization' );

		if ( empty( $auth_header ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Missing Authorization header.', 'update-watchdog' ),
				array( 'status' => 401 )
			);
		}

		if ( ! preg_match( '/^Bearer\s+(\S+)$/i', $auth_header, $matches ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid Authorization header format. Expected: Bearer {TOKEN}', 'update-watchdog' ),
				array( 'status' => 401 )
			);
		}

		$provided_token = $matches[1];
		$stored_token   = get_option( Updawa_Admin::TOKEN_OPTION );

		if ( empty( $stored_token ) || ! is_string( $stored_token ) || ! hash_equals( $stored_token, $provided_token ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid token.', 'update-watchdog' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Callback for GET /wp-json/update-watchdog/v1/status
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status( WP_REST_Request $request ) {
		try {
			$status   = $this->updater->get_status();
			$response = new WP_REST_Response( $status, 200 );
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
			$response->header( 'Vary', 'Authorization' );
			return $response;

        } catch ( Exception $e ) {
			return new WP_Error(
				'internal_error',
				__( 'Internal error while fetching update data.', 'update-watchdog' ),
				array( 'status' => 500 )
			);
		}
	}
}
