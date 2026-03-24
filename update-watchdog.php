<?php
/**
 * Plugin Name: UpdaWa
 * Plugin URI:  https://github.com/Designer-Solutions/update-watchdog
 * Description: Monitors the availability of updates for WordPress plugins, themes, and core. Exposes results in the admin panel and via a REST API secured with a Bearer token.
 * Version:     1.0.2
 * Author:      Designer Solutions sp. z o.o.
 * Author URI:  https://github.com/Designer-Solutions
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: updawa
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.0
 */

/*
 * UpdaWa – WordPress update monitor
 * Copyright (C) 2024  Designer Solutions sp. z o.o.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, see <https://www.gnu.org/licenses/>.
 */

defined( 'ABSPATH' ) || exit;

define( 'UPDAWA_VERSION', '1.0.2' );
define( 'UPDAWA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UPDAWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once UPDAWA_PLUGIN_DIR . 'includes/class-updawa-updater.php';
require_once UPDAWA_PLUGIN_DIR . 'includes/class-updawa-admin.php';
require_once UPDAWA_PLUGIN_DIR . 'includes/class-updawa-api.php';


function updawa_init() {
	$updater = new Updawa_Updater();
	$admin   = new Updawa_Admin( $updater );
	$api     = new Updawa_API( $updater );

	$admin->init();
	$api->init();
}
add_action( 'plugins_loaded', 'updawa_init' );
