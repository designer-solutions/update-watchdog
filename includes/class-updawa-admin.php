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
		add_filter( 'plugins_api', array( $this, 'plugin_api_info' ), 10, 3 );
	}

	/**
	 * Supplies plugin metadata (including icons) when WordPress queries this plugin's info.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object
	 */
	public function plugin_api_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || 'updawa' !== $args->slug ) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = 'UpdaWa';
		$info->slug          = 'updawa';
		$info->version       = UPDAWA_VERSION;
		$info->author        = '<a href="https://github.com/Designer-Solutions">Designer Solutions sp. z o.o.</a>';
		$info->author_profile = 'https://github.com/Designer-Solutions';
		$info->homepage      = 'https://github.com/Designer-Solutions/update-watchdog';
		$info->requires      = '6.0';
		$info->tested        = '6.9';
		$info->requires_php  = '7.0';
		$info->short_description = __( 'Monitors the availability of updates for WordPress plugins, themes, and core. Exposes results via a REST API secured with a Bearer token.', 'updawa' );
		$info->sections      = array(
			'description' => '<p>' . __( 'UpdaWa (Update Watchdog) monitors available updates for your WordPress plugins, themes, and core installation. It provides a clear admin dashboard with update status, SSL certificate expiry monitoring, and a REST API endpoint secured with a Bearer token — perfect for integrating with external monitoring tools and mobile apps.', 'updawa' ) . '</p>'
				. '<h4>' . __( 'Features', 'updawa' ) . '</h4>'
				. '<ul>'
				. '<li>' . __( 'Dashboard with update status for plugins, themes, and WordPress core', 'updawa' ) . '</li>'
				. '<li>' . __( 'SSL certificate expiry monitoring', 'updawa' ) . '</li>'
				. '<li>' . __( 'REST API endpoint with Bearer token authentication', 'updawa' ) . '</li>'
				. '<li>' . __( 'QR code for easy mobile app configuration', 'updawa' ) . '</li>'
				. '<li>' . __( 'JSON export of the full update status', 'updawa' ) . '</li>'
				. '</ul>',
			'changelog'   => '<h4>1.0.5</h4><ul><li>' . __( 'Fixed false positive update notifications for plugins and themes.', 'updawa' ) . '</li></ul>'
				. '<h4>1.0.4</h4><ul><li>' . __( 'Improved plugin information display.', 'updawa' ) . '</li></ul>'
				. '<h4>1.0.0</h4><ul><li>' . __( 'Initial release.', 'updawa' ) . '</li></ul>',
		);
		$info->icons         = array(
			'1x' => UPDAWA_PLUGIN_URL . 'assets/images/icon-128x128.png',
			'2x' => UPDAWA_PLUGIN_URL . 'assets/images/icon-256x256.png',
		);

		return $info;
	}

	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_updawa' !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'qrcodejs',
			UPDAWA_PLUGIN_URL . 'assets/js/qrcode.min.js',
			array(),
			UPDAWA_VERSION,
			true
		);
		wp_enqueue_style(
			'updawa-admin',
			UPDAWA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			UPDAWA_VERSION
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

		$tabs = array(
			'table' => array(
				'label' => __( 'Status', 'updawa' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
			),
			'json'  => array(
				'label' => __( 'JSON', 'updawa' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
			),
			'token' => array(
				'label' => __( 'Token API', 'updawa' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
			),
		);
		?>
		<div class="wrap" id="updawa-page">

			<div class="uw-header">
				<div class="uw-header-left">
					<img src="<?php echo esc_url( UPDAWA_PLUGIN_URL . 'assets/images/icon-128x128.png' ); ?>"
					     alt="<?php esc_attr_e( 'UpdaWa', 'updawa' ); ?>"
					     class="uw-header-icon">
					<div>
						<div class="uw-header-title">
							<?php esc_html_e( 'UpdaWa', 'updawa' ); ?>
							<span class="uw-version-badge">v<?php echo esc_html( UPDAWA_VERSION ); ?></span>
						</div>
						<div class="uw-header-sub"><?php esc_html_e( 'Update Watchdog', 'updawa' ); ?></div>
					</div>
				</div>
			</div>

			<nav class="uw-tabs" role="tablist">
				<?php foreach ( $tabs as $slug => $tab ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=updawa&tab=' . $slug ) ); ?>"
				   class="uw-tab <?php echo $slug === $active_tab ? 'is-active' : ''; ?>"
				   role="tab">
					<?php echo $tab['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG. ?>
					<?php echo esc_html( $tab['label'] ); ?>
				</a>
				<?php endforeach; ?>
			</nav>

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

		// Count pending updates for stat cards.
		$core_updates   = $status['wordpress']['update_available'] ? 1 : 0;
		$plugin_updates = count( array_filter( $status['plugins'], function( $p ) { return $p['update_available']; } ) );
		$theme_updates  = count( array_filter( $status['themes'],  function( $t ) { return $t['update_available']; } ) );
		$total_updates  = $core_updates + $plugin_updates + $theme_updates;

		// SSL state.
		$ssl_badge = 'muted';
		if ( isset( $status['ssl_expires_at'] ) ) {
			$ssl_days  = (int) floor( ( strtotime( $status['ssl_expires_at'] ) - time() ) / DAY_IN_SECONDS );
			$ssl_badge = $ssl_days < 0 ? 'err' : ( $ssl_days <= 30 ? 'warn' : 'ok' );
		}
		?>

		<!-- Stat cards -->
		<div class="uw-stats">
			<div class="uw-stat <?php echo $total_updates > 0 ? 'is-warn' : ''; ?>">
				<div class="uw-stat-icon">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.68"/></svg>
				</div>
				<div>
					<div class="uw-stat-num <?php echo $total_updates > 0 ? 'is-warn' : ''; ?>"><?php echo esc_html( $total_updates ); ?></div>
					<div class="uw-stat-label"><?php esc_html_e( 'Pending updates', 'updawa' ); ?></div>
				</div>
			</div>
			<div class="uw-stat">
				<div class="uw-stat-icon">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
				</div>
				<div>
					<div class="uw-stat-num"><?php echo esc_html( count( $status['plugins'] ) ); ?></div>
					<div class="uw-stat-label"><?php esc_html_e( 'Plugins', 'updawa' ); ?></div>
				</div>
			</div>
			<div class="uw-stat">
				<div class="uw-stat-icon">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
				</div>
				<div>
					<div class="uw-stat-num"><?php echo esc_html( count( $status['themes'] ) ); ?></div>
					<div class="uw-stat-label"><?php esc_html_e( 'Themes', 'updawa' ); ?></div>
				</div>
			</div>
			<div class="uw-stat <?php echo 'ok' !== $ssl_badge ? 'is-warn' : ''; ?>">
				<div class="uw-stat-icon">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
				</div>
				<div>
					<div class="uw-stat-num <?php echo 'ok' !== $ssl_badge ? 'is-warn' : ''; ?>">
						<?php
						if ( 'ok' === $ssl_badge ) {
							echo esc_html( $ssl_days . 'd' );
						} elseif ( 'warn' === $ssl_badge ) {
							echo esc_html( $ssl_days . 'd' );
						} else {
							esc_html_e( '!', 'updawa' );
						}
						?>
					</div>
					<div class="uw-stat-label"><?php esc_html_e( 'SSL remaining', 'updawa' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Toolbar -->
		<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap;">
			<p style="margin:0;font-size:12px;color:var(--uw-muted);">
				<?php esc_html_e( 'Generated:', 'updawa' ); ?>
				<strong style="color:var(--uw-text);"><?php echo esc_html( $this->format_datetime( $status['generated_at'] ) ); ?></strong>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
				<?php wp_nonce_field( 'updawa_refresh', 'updawa_refresh_nonce' ); ?>
				<input type="hidden" name="action" value="updawa_refresh">
				<input type="hidden" name="return_tab" value="table">
				<button type="submit" class="uw-btn uw-btn-primary">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.68"/></svg>
					<?php esc_html_e( 'Refresh', 'updawa' ); ?>
				</button>
			</form>
		</div>

		<!-- WordPress core -->
		<div class="uw-card">
			<div class="uw-card-head">
				<div class="uw-card-title">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
					<?php esc_html_e( 'WordPress Core', 'updawa' ); ?>
				</div>
				<?php if ( $status['wordpress']['update_available'] ) : ?>
					<span class="uw-badge uw-badge-warn"><span class="uw-badge-dot"></span><?php esc_html_e( 'Update available', 'updawa' ); ?></span>
				<?php else : ?>
					<span class="uw-badge uw-badge-ok"><span class="uw-badge-dot"></span><?php esc_html_e( 'Up to date', 'updawa' ); ?></span>
				<?php endif; ?>
			</div>
			<table class="uw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Current version', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'New version', 'updawa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr<?php echo $status['wordpress']['update_available'] ? ' class="is-warn"' : ''; ?>>
						<td><?php echo esc_html( $status['wordpress']['current_version'] ); ?></td>
						<td>
							<?php if ( $status['wordpress']['update_available'] ) : ?>
								<span class="uw-badge uw-badge-warn"><span class="uw-badge-dot"></span><?php esc_html_e( 'Update available', 'updawa' ); ?></span>
							<?php else : ?>
								<span class="uw-badge uw-badge-ok"><span class="uw-badge-dot"></span><?php esc_html_e( 'Up to date', 'updawa' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo $status['wordpress']['new_version'] ? esc_html( $status['wordpress']['new_version'] ) : '<span style="color:var(--uw-muted2)">&ndash;</span>'; ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- SSL -->
		<div class="uw-card">
			<div class="uw-card-head">
				<div class="uw-card-title">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
					<?php esc_html_e( 'SSL Certificate', 'updawa' ); ?>
				</div>
				<?php if ( 'ok' === $ssl_badge ) : ?>
					<span class="uw-badge uw-badge-ok"><span class="uw-badge-dot"></span><?php esc_html_e( 'Valid', 'updawa' ); ?></span>
				<?php elseif ( 'warn' === $ssl_badge ) : ?>
					<span class="uw-badge uw-badge-warn"><span class="uw-badge-dot"></span><?php esc_html_e( 'Expiring soon', 'updawa' ); ?></span>
				<?php elseif ( 'err' === $ssl_badge ) : ?>
					<span class="uw-badge uw-badge-err"><span class="uw-badge-dot"></span><?php esc_html_e( 'Expired', 'updawa' ); ?></span>
				<?php else : ?>
					<span class="uw-badge uw-badge-muted"><?php esc_html_e( 'Not available', 'updawa' ); ?></span>
				<?php endif; ?>
			</div>
			<table class="uw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Status', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Days remaining', 'updawa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! isset( $status['ssl_expires_at'] ) ) : ?>
						<tr>
							<td><span class="uw-badge uw-badge-muted"><?php esc_html_e( 'Not available', 'updawa' ); ?></span></td>
							<td style="color:var(--uw-muted2)">&ndash;</td>
							<td style="color:var(--uw-muted2)">&ndash;</td>
						</tr>
					<?php else :
						$expiry_ts = strtotime( $status['ssl_expires_at'] );
						$days_left = (int) floor( ( $expiry_ts - time() ) / DAY_IN_SECONDS );
						$expired   = $days_left < 0;
						$expiring  = ! $expired && $days_left <= 30;
						?>
						<tr<?php echo ( $expired || $expiring ) ? ' class="is-warn"' : ''; ?>>
							<td>
								<?php if ( $expired ) : ?>
									<span class="uw-badge uw-badge-err"><span class="uw-badge-dot"></span><?php esc_html_e( 'Expired', 'updawa' ); ?></span>
								<?php elseif ( $expiring ) : ?>
									<span class="uw-badge uw-badge-warn"><span class="uw-badge-dot"></span><?php esc_html_e( 'Expiring soon', 'updawa' ); ?></span>
								<?php else : ?>
									<span class="uw-badge uw-badge-ok"><span class="uw-badge-dot"></span><?php esc_html_e( 'Valid', 'updawa' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $this->format_datetime( $status['ssl_expires_at'] ) ); ?></td>
							<td><?php echo $expired ? esc_html( abs( $days_left ) . ' ' . __( 'days ago', 'updawa' ) ) : esc_html( $days_left . ' ' . __( 'days', 'updawa' ) ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Plugins -->
		<div class="uw-card">
			<div class="uw-card-head">
				<div class="uw-card-title">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
					<?php esc_html_e( 'Plugins', 'updawa' ); ?>
				</div>
				<?php if ( $plugin_updates > 0 ) : ?>
					<span class="uw-badge uw-badge-warn"><span class="uw-badge-dot"></span><?php echo esc_html( $plugin_updates . ' ' . _n( 'update', 'updates', $plugin_updates, 'updawa' ) ); ?></span>
				<?php else : ?>
					<span class="uw-badge uw-badge-ok"><span class="uw-badge-dot"></span><?php esc_html_e( 'All up to date', 'updawa' ); ?></span>
				<?php endif; ?>
			</div>
			<table class="uw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Version', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'New version', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Active', 'updawa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $status['plugins'] as $plugin ) : ?>
					<tr<?php echo $plugin['update_available'] ? ' class="is-warn"' : ''; ?>>
						<td style="font-weight:500;"><?php echo esc_html( $plugin['name'] ); ?></td>
						<td><code><?php echo esc_html( $plugin['slug'] ); ?></code></td>
						<td><?php echo esc_html( $plugin['current_version'] ); ?></td>
						<td>
							<?php if ( $plugin['update_available'] ) : ?>
								<span class="uw-badge uw-badge-warn"><span class="uw-badge-dot"></span><?php esc_html_e( 'Update', 'updawa' ); ?></span>
							<?php else : ?>
								<span class="uw-badge uw-badge-ok"><span class="uw-badge-dot"></span><?php esc_html_e( 'OK', 'updawa' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo $plugin['new_version'] ? esc_html( $plugin['new_version'] ) : '<span style="color:var(--uw-muted2)">&ndash;</span>'; ?></td>
						<td>
							<?php if ( $plugin['active'] ) : ?>
								<span class="uw-pill-active"><?php esc_html_e( 'Active', 'updawa' ); ?></span>
							<?php else : ?>
								<span class="uw-pill-inactive"><?php esc_html_e( 'Inactive', 'updawa' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Themes -->
		<div class="uw-card">
			<div class="uw-card-head">
				<div class="uw-card-title">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
					<?php esc_html_e( 'Themes', 'updawa' ); ?>
				</div>
				<?php if ( $theme_updates > 0 ) : ?>
					<span class="uw-badge uw-badge-warn"><span class="uw-badge-dot"></span><?php echo esc_html( $theme_updates . ' ' . _n( 'update', 'updates', $theme_updates, 'updawa' ) ); ?></span>
				<?php else : ?>
					<span class="uw-badge uw-badge-ok"><span class="uw-badge-dot"></span><?php esc_html_e( 'All up to date', 'updawa' ); ?></span>
				<?php endif; ?>
			</div>
			<table class="uw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Version', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'New version', 'updawa' ); ?></th>
						<th><?php esc_html_e( 'Active', 'updawa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $status['themes'] as $theme ) : ?>
					<tr<?php echo $theme['update_available'] ? ' class="is-warn"' : ''; ?>>
						<td style="font-weight:500;"><?php echo esc_html( $theme['name'] ); ?></td>
						<td><code><?php echo esc_html( $theme['slug'] ); ?></code></td>
						<td><?php echo esc_html( $theme['current_version'] ); ?></td>
						<td>
							<?php if ( $theme['update_available'] ) : ?>
								<span class="uw-badge uw-badge-warn"><span class="uw-badge-dot"></span><?php esc_html_e( 'Update', 'updawa' ); ?></span>
							<?php else : ?>
								<span class="uw-badge uw-badge-ok"><span class="uw-badge-dot"></span><?php esc_html_e( 'OK', 'updawa' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo $theme['new_version'] ? esc_html( $theme['new_version'] ) : '<span style="color:var(--uw-muted2)">&ndash;</span>'; ?></td>
						<td>
							<?php if ( $theme['active'] ) : ?>
								<span class="uw-pill-active"><?php esc_html_e( 'Active', 'updawa' ); ?></span>
							<?php else : ?>
								<span class="uw-pill-inactive"><?php esc_html_e( 'Inactive', 'updawa' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php
	}

	/**
	 * JSON tab: pretty-printed status output and Refresh button.
	 */
	private function render_status_json_tab() {
		$status = $this->updater->get_status();
		$json   = wp_json_encode( $status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		?>
		<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap;">
			<p style="margin:0;font-size:12px;color:var(--uw-muted);">
				<?php esc_html_e( 'Raw update-status payload — useful for debugging or copying into external tools.', 'updawa' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
				<?php wp_nonce_field( 'updawa_refresh', 'updawa_refresh_nonce' ); ?>
				<input type="hidden" name="action" value="updawa_refresh">
				<input type="hidden" name="return_tab" value="json">
				<button type="submit" class="uw-btn uw-btn-primary">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.68"/></svg>
					<?php esc_html_e( 'Refresh', 'updawa' ); ?>
				</button>
			</form>
		</div>

		<div class="uw-card">
			<div class="uw-card-head">
				<div class="uw-card-title">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
					status.json
				</div>
				<button type="button" id="uw-copy-json" class="uw-copy-btn">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
					<?php esc_html_e( 'Copy', 'updawa' ); ?>
				</button>
			</div>
			<pre id="uw-json-ta" class="uw-json-pre"><?php echo esc_html( $json ); ?></pre>
		</div>
		<script>
		document.getElementById('uw-copy-json').addEventListener('click', function() {
			var ta  = document.getElementById('uw-json-ta');
			var btn = this;
			navigator.clipboard.writeText(ta.textContent).then(function() {
				btn.textContent = '✓ Copied';
				setTimeout(function() { btn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy'; }, 2000);
			});
		});
		</script>
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

		$site_name   = mb_substr( get_bloginfo( 'name' ), 0, 100, 'UTF-8' );
		$api_url     = rest_url( 'updawa/v1/status' );
		$qr_data     = wp_json_encode(
			array(
				'name'  => $site_name,
				'url'   => $api_url,
				'token' => $token,
			),
			JSON_UNESCAPED_SLASHES
		);
		?>
		<?php if ( $regenerated ) : ?>
			<div class="notice notice-warning is-dismissible">
				<p><?php esc_html_e( 'Token regenerated. The previous token is no longer valid.', 'updawa' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="uw-token-layout">
			<div>
				<!-- Token card -->
				<div class="uw-card" style="margin-bottom:20px;">
					<div class="uw-card-head">
						<div class="uw-card-title">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
							<?php esc_html_e( 'Bearer Token', 'updawa' ); ?>
						</div>
						<span class="uw-badge uw-badge-ok"><span class="uw-badge-dot"></span><?php esc_html_e( 'Active', 'updawa' ); ?></span>
					</div>
					<div class="uw-card-body">
						<div class="uw-token-field-wrap">
							<input type="text"
							       id="uw-token-field"
							       readonly
							       value="<?php echo esc_attr( $token ); ?>"
							       class="uw-token-input"
							       onclick="this.select();" />
							<button type="button" id="uw-copy-token" class="uw-copy-btn">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
								<?php esc_html_e( 'Copy', 'updawa' ); ?>
							</button>
						</div>
						<p class="uw-token-hint">
							<?php esc_html_e( 'Use in HTTP header:', 'updawa' ); ?>
							<code>Authorization: Bearer {TOKEN}</code>
						</p>
					</div>
				</div>

				<!-- Regenerate card -->
				<div class="uw-card" style="margin-bottom:20px;">
					<div class="uw-card-head">
						<div class="uw-card-title">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.68"/></svg>
							<?php esc_html_e( 'Regenerate Token', 'updawa' ); ?>
						</div>
					</div>
					<div class="uw-card-body">
						<p style="font-size:12px;color:var(--uw-muted);margin:0 0 14px;">
							<?php esc_html_e( 'Generates a new cryptographically secure token. The current token will stop working immediately.', 'updawa' ); ?>
						</p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
							<?php wp_nonce_field( 'updawa_regenerate_token', 'updawa_regenerate_nonce' ); ?>
							<input type="hidden" name="action" value="updawa_regenerate_token">
							<button type="submit"
							        class="uw-btn uw-btn-danger"
							        onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to regenerate the token? The previous token will stop working immediately.', 'updawa' ) ); ?>');">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.68"/></svg>
								<?php esc_html_e( 'Regenerate token', 'updawa' ); ?>
							</button>
						</form>
					</div>
				</div>

				<!-- cURL example card -->
				<div class="uw-card">
					<div class="uw-card-head">
						<div class="uw-card-title">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
							<?php esc_html_e( 'Example API Call', 'updawa' ); ?>
						</div>
						<button type="button" id="uw-copy-curl" class="uw-copy-btn">
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
							<?php esc_html_e( 'Copy', 'updawa' ); ?>
						</button>
					</div>
					<div class="uw-card-body" style="padding:0;">
						<div class="uw-curl-block" id="uw-curl-text" data-curl="curl -H &quot;Authorization: Bearer <?php echo esc_attr( $token ); ?>&quot; <?php echo esc_url( rest_url( 'updawa/v1/status' ) ); ?>">
							<span class="uw-curl-cmd">curl</span>
							<span class="uw-curl-flag"> -H </span>
							<span class="uw-curl-str">"Authorization: Bearer <?php echo esc_html( $token ); ?>"</span>
							<span class="uw-curl-flag"> \</span><br>
							&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="uw-curl-url"><?php echo esc_url( rest_url( 'updawa/v1/status' ) ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<!-- QR code column -->
			<div class="uw-card" style="min-width:200px;">
				<div class="uw-card-head">
					<div class="uw-card-title">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 17h3M17 14v3M21 17h.01M21 14v.01M14 21h.01"/></svg>
						<?php esc_html_e( 'QR Code', 'updawa' ); ?>
					</div>
				</div>
				<div class="uw-card-body" style="display:flex;flex-direction:column;align-items:center;gap:12px;">
					<div class="uw-qr-box">
						<div id="updawa-qr"></div>
					</div>
					<p style="font-size:11px;color:var(--uw-muted);text-align:center;margin:0;max-width:180px;line-height:1.6;">
						<?php esc_html_e( 'Scan to import site URL and token into a monitoring app.', 'updawa' ); ?>
					</p>
				</div>
			</div>
		</div>

		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			var size = Math.min( 200, Math.floor( window.innerHeight / 4 ) );
			new QRCode( document.getElementById( 'updawa-qr' ), {
				text: <?php echo wp_json_encode( $qr_data ); ?>,
				width: size,
				height: size,
				colorDark: '#111827',
				colorLight: '#ffffff',
				correctLevel: QRCode.CorrectLevel.M
			} );

			document.getElementById('uw-copy-token').addEventListener('click', function() {
				var field = document.getElementById('uw-token-field');
				var btn   = this;
				navigator.clipboard.writeText(field.value).then(function() {
					btn.classList.add('copied');
					btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Copied';
					setTimeout(function() {
						btn.classList.remove('copied');
						btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy';
					}, 2000);
				});
			});

			document.getElementById('uw-copy-curl').addEventListener('click', function() {
				var text = document.getElementById('uw-curl-text').dataset.curl;
				var btn  = this;
				navigator.clipboard.writeText(text).then(function() {
					btn.classList.add('copied');
					btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Copied';
					setTimeout(function() {
						btn.classList.remove('copied');
						btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy';
					}, 2000);
				});
			});
		} );
		</script>
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
