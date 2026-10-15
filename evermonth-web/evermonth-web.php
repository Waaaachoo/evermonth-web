<?php
/**
 * Plugin Name:       Evermonth Web
 * Plugin URI:        https://github.com/Waaaachoo/evermonth-web
 * Description:       Sirve el sitio nuevo de Campamento Evermonth (inicio y Halloween) dentro de WordPress. Se actualiza desde GitHub. Apagarlo devuelve el sitio anterior.
 * Version:           1.2.0
 * Author:            Made by Wacho
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Update URI:        https://github.com/Waaaachoo/evermonth-web
 * Text Domain:       evermonth-web
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EVW_VERSION', '1.2.0' );
define( 'EVW_FILE', __FILE__ );
define( 'EVW_DIR', plugin_dir_path( __FILE__ ) );
define( 'EVW_BASENAME', plugin_basename( __FILE__ ) );
define( 'EVW_REPO', 'Waaaachoo/evermonth-web' );
define( 'EVW_ASSET', 'evermonth-web.zip' );

/**
 * Settings, with defaults. Both switches start OFF, so activating the plugin changes nothing for visitors.
 * - live:  the new site replaces the WordPress pages for everyone.
 * - index: Google may index the new pages (removes the noindex tag).
 */
function evw_settings() {
	$s = get_option( 'evw_settings', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), array( 'live' => 0, 'index' => 0 ) );
}

require EVW_DIR . 'inc/router.php';
require EVW_DIR . 'inc/updater.php';

if ( is_admin() ) {
	require EVW_DIR . 'inc/admin.php';
}
