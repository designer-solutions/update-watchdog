<?php
/**
 * Handles the admin menu, tabbed screen, and API token management.
 *
 * @package WP_Watchdog
 */

defined( 'ABSPATH' ) || exit;

class Updawa_Admin {

	const TOKEN_OPTION = 'updawa_token';

	/**
	 * @var Updawa_Updater
	 */
	private $updater;

	public function __construct(Updawa_Updater $updater ) {
		$this->updater = $updater;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_updawa_regenerate_token', array( $this, 'handle_regenerate_token' ) );
		add_action( 'admin_post_updawa_refresh', array( $this, 'handle_refresh' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_updawa' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'qrcodejs',
			UPDAWA_PLUGIN_URL . 'assets/js/qrcode.min.js',
			array(),
			'1.0.2',
			true
		);
	}

	public function register_menu() {
		add_menu_page(
			__( 'UpdaWa - the update watchdog', 'updawa' ),
			__( 'UpdaWa - the update watchdog', 'updawa' ),
			'manage_options',
			'updawa',
			array( $this, 'render_page' ),
			'dashicons-visibility',
			80
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameter, no data is modified.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'table';

		if ( in_array( $active_tab, array( 'json', 'table' ), true ) ) {
			$this->updater->force_refresh();
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'UpdaWa', 'updawa' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=updawa&tab=table' ) ); ?>"
				   class="nav-tab <?php echo 'table' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Status', 'updawa' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=updawa&tab=json' ) ); ?>"
				   class="nav-tab <?php echo 'json' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'JSON', 'updawa' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=updawa&tab=token' ) ); ?>"
				   class="nav-tab <?php echo 'token' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Token API', 'updawa' ); ?>
				</a>
			</nav>

			<div style="margin-top: 20px;">
				<?php
				if ( 'table' === $active_tab ) {
					$this->render_status_table_tab();
				} elseif ( 'json' === $active_tab ) {
					$this->render_status_json_tab();
				} elseif ( 'token' === $active_tab ) {
					$this->render_token_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Formats an ATOM/ISO 8601 date string into a human-readable local string.
	 *
	 * @param string $atom_date
	 * @return string
	 */
	private function format_datetime( $atom_date ) {
		$ts = strtotime( $atom_date );
		if ( false === $ts ) {
			return $atom_date;
		}
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}

	/**
	 * Table tab: update status rendered as HTML tables.
	 */
	private function render_status_table_tab() {
		$status = $this->updater->get_status();
		?>
		<p class="description">
			<?php esc_html_e( 'An overview of all installed plugins, themes, and WordPress core — showing current versions and whether updates are available. Rows highlighted in yellow require attention.', 'updawa' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'updawa_refresh', 'updawa_refresh_nonce' ); ?>
			<input type="hidden" name="action" value="updawa_refresh">
			<input type="hidden" name="return_tab" value="table">
			<p>
				<button type="submit" class="button button-secondary">
					<?php esc_html_e( 'Refresh', 'updawa' ); ?>
				</button>
			</p>
		</form>

		<p><strong><?php esc_html_e( 'Generated:', 'updawa' ); ?></strong> <?php echo esc_html( $this->format_datetime( $status['generated_at'] ) ); ?></p>

		<h3><?php esc_html_e( 'WordPress (core)', 'updawa' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Current version', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'Update available', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'New version', 'updawa' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr<?php echo $status['wordpress']['update_available'] ? ' style="background-color: #fff8c5 !important;"' : ''; ?>>
					<td><?php echo esc_html( $status['wordpress']['current_version'] ); ?></td>
					<td>
						<?php if ( $status['wordpress']['update_available'] ) : ?>
							<span style="color: #d63638;">&#10007; <?php esc_html_e( 'Yes', 'updawa' ); ?></span>
						<?php else : ?>
							<span style="color: #00a32a;">&#10003; <?php esc_html_e( 'No', 'updawa' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo $status['wordpress']['new_version'] ? esc_html( $status['wordpress']['new_version'] ) : '&ndash;'; ?></td>
				</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Plugins', 'updawa' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'Version', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'Update', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'New version', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'Active', 'updawa' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $status['plugins'] as $plugin ) : ?>
				<tr<?php echo $plugin['update_available'] ? ' style="background-color: #fff8c5 !important;"' : ''; ?>>
					<td><?php echo esc_html( $plugin['name'] ); ?></td>
					<td><code><?php echo esc_html( $plugin['slug'] ); ?></code></td>
					<td><?php echo esc_html( $plugin['current_version'] ); ?></td>
					<td>
						<?php if ( $plugin['update_available'] ) : ?>
							<span style="color: #d63638;">&#10007; <?php esc_html_e( 'Yes', 'updawa' ); ?></span>
						<?php else : ?>
							<span style="color: #00a32a;">&#10003; <?php esc_html_e( 'No', 'updawa' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo $plugin['new_version'] ? esc_html( $plugin['new_version'] ) : '&ndash;'; ?></td>
					<td>
						<?php echo $plugin['active'] ? esc_html__( 'Yes', 'updawa' ) : esc_html__( 'No', 'updawa' ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Themes', 'updawa' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'Version', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'Update', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'New version', 'updawa' ); ?></th>
					<th><?php esc_html_e( 'Active', 'updawa' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $status['themes'] as $theme ) : ?>
				<tr<?php echo $theme['update_available'] ? ' style="background-color: #fff8c5 !important;"' : ''; ?>>
					<td><?php echo esc_html( $theme['name'] ); ?></td>
					<td><code><?php echo esc_html( $theme['slug'] ); ?></code></td>
					<td><?php echo esc_html( $theme['current_version'] ); ?></td>
					<td>
						<?php if ( $theme['update_available'] ) : ?>
							<span style="color: #d63638;">&#10007; <?php esc_html_e( 'Yes', 'updawa' ); ?></span>
						<?php else : ?>
							<span style="color: #00a32a;">&#10003; <?php esc_html_e( 'No', 'updawa' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo $theme['new_version'] ? esc_html( $theme['new_version'] ) : '&ndash;'; ?></td>
					<td>
						<?php echo $theme['active'] ? esc_html__( 'Yes', 'updawa' ) : esc_html__( 'No', 'updawa' ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * JSON tab: pretty-printed status output and Refresh button.
	 */
	private function render_status_json_tab() {
		$status = $this->updater->get_status();
		$json   = wp_json_encode( $status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		?>
		<p class="description">
			<?php esc_html_e( 'The raw update-status data as JSON — useful for debugging or copying into external tools.', 'updawa' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'updawa_refresh', 'updawa_refresh_nonce' ); ?>
			<input type="hidden" name="action" value="updawa_refresh">
			<input type="hidden" name="return_tab" value="json">
			<p>
				<button type="submit" class="button button-secondary">
					<?php esc_html_e( 'Refresh', 'updawa' ); ?>
				</button>
			</p>
		</form>

		<textarea readonly
		          style="width: 100%; height: 600px; font-family: monospace; font-size: 13px; background: #f0f0f1; border: 1px solid #c3c4c7; padding: 10px; box-sizing: border-box;"
		><?php echo esc_textarea( $json ); ?></textarea>
		<?php
	}

	/**
	 * Token API tab: token field, QR code, and Regenerate button.
	 */
	private function render_token_tab() {
		$token = get_option( self::TOKEN_OPTION );
		if ( ! $token ) {
			$token = $this->generate_token();
			update_option( self::TOKEN_OPTION, $token );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag set by wp_safe_redirect() after token regeneration, no data is modified.
		$regenerated = isset( $_GET['regenerated'] ) && '1' === $_GET['regenerated'];

		$site_name = mb_substr( get_bloginfo( 'name' ), 0, 100, 'UTF-8' );
		$site_url  = get_site_url();
		$qr_data   = wp_json_encode(
			array(
				'name'  => $site_name,
				'url'   => $site_url,
				'token' => $token,
			),
			JSON_UNESCAPED_SLASHES
		);
		?>
		<p class="description">
			<?php esc_html_e( 'Manage the Bearer token used to authenticate REST API requests. Scan the QR code to import connection details into a monitoring app.', 'updawa' ); ?>
		</p>

		<?php if ( $regenerated ) : ?>
			<div class="notice notice-warning is-dismissible">
				<p><?php esc_html_e( 'Token regenerated. The previous token is no longer valid.', 'updawa' ); ?></p>
			</div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Current token', 'updawa' ); ?></th>
				<td>
					<input type="text"
					       readonly
					       value="<?php echo esc_attr( $token ); ?>"
					       style="width: 600px; font-family: monospace; font-size: 13px;"
					       onclick="this.select();" />
					<p class="description">
						<?php esc_html_e( 'Click to select. Use the token in the HTTP header: Authorization: Bearer {TOKEN}', 'updawa' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'updawa_regenerate_token', 'updawa_regenerate_nonce' ); ?>
			<input type="hidden" name="action" value="updawa_regenerate_token">
			<p>
				<button type="submit"
				        class="button button-secondary"
				        onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to regenerate the token? The previous token will stop working immediately.', 'updawa' ) ); ?>');">
					<?php esc_html_e( 'Regenerate token', 'updawa' ); ?>
				</button>
			</p>
		</form>

		<h3><?php esc_html_e( 'QR Code', 'updawa' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'The QR code contains: site name, site URL, and API token.', 'updawa' ); ?>
		</p>
		<div id="updawa-qr" style="margin: 15px 0; display: inline-block;"></div>
		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			var size = Math.floor( window.innerHeight / 3 );
			new QRCode( document.getElementById( 'updawa-qr' ), {
				text: <?php echo wp_json_encode( $qr_data ); ?>,
				width: size,
				height: size,
				colorDark: '#000000',
				colorLight: '#ffffff',
				correctLevel: QRCode.CorrectLevel.M
			} );
		} );
		</script>

		<h3><?php esc_html_e( 'Example REST API call', 'updawa' ); ?></h3>
		<pre style="background: #f0f0f1; padding: 15px; border-left: 4px solid #0073aa; overflow-x: auto; font-size: 13px;">curl -H "Authorization: Bearer <?php echo esc_html( $token ); ?>" \
     <?php echo esc_url( rest_url( 'updawa/v1/status' ) ); ?></pre>
		<?php
	}

	/**
	 * POST handler: token regeneration.
	 */
	public function handle_regenerate_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'updawa' ) );
		}

		check_admin_referer( 'updawa_regenerate_token', 'updawa_regenerate_nonce' );

		$token = $this->generate_token();
		update_option( self::TOKEN_OPTION, $token );

		wp_safe_redirect( admin_url( 'admin.php?page=updawa&tab=token&regenerated=1' ) );
		exit;
	}

	/**
	 * POST handler: manual update-status refresh.
	 */
	public function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'updawa' ) );
		}

		check_admin_referer( 'updawa_refresh', 'updawa_refresh_nonce' );

		$this->updater->force_refresh();

		$return_tab = ( isset( $_POST['return_tab'] ) && 'json' === $_POST['return_tab'] ) ? 'json' : 'table';
		wp_safe_redirect( admin_url( 'admin.php?page=updawa&tab=' . $return_tab ) );
		exit;
	}

	/**
	 * Generates a cryptographically secure token (64 hex characters).
	 *
	 * @return string
	 */
	private function generate_token() {
		return bin2hex( random_bytes( 32 ) );
	}
}
