<?php
/**
 * Updates from GitHub. The plugin header "Update URI" points to github.com, so WordPress
 * asks the update_plugins_github.com filter instead of wordpress.org.
 * A new GitHub release (tag v1.2.3 + asset evermonth-web.zip) shows up as a normal
 * plugin update in Plugins and Escritorio > Actualizaciones.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const EVW_RELEASE_CACHE = 'evw_release';

/** Latest release, cached 6 hours. Returns null when GitHub does not answer. */
function evw_latest_release( $force = false ) {
	if ( ! $force ) {
		$cached = get_site_transient( EVW_RELEASE_CACHE );
		if ( is_array( $cached ) ) {
			return $cached['version'] ? $cached : null;
		}
	}

	$res  = wp_remote_get(
		'https://api.github.com/repos/' . EVW_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'evermonth-web/' . EVW_VERSION,
			),
		)
	);
	$data = null;
	if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_array( $json ) && ! empty( $json['tag_name'] ) && ! empty( $json['assets'] ) ) {
			foreach ( $json['assets'] as $asset ) {
				if ( EVW_ASSET === $asset['name'] ) {
					$data = array(
						'version'   => ltrim( $json['tag_name'], 'vV' ),
						'package'   => $asset['browser_download_url'],
						'url'       => $json['html_url'],
						'notes'     => (string) $json['body'],
						'published' => (string) $json['published_at'],
					);
					break;
				}
			}
		}
	}

	// Cache a miss for 1 hour, so a GitHub outage does not slow every admin page.
	set_site_transient( EVW_RELEASE_CACHE, $data ? $data : array( 'version' => '' ), $data ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $data;
}

add_filter( 'update_plugins_github.com', 'evw_update_check', 10, 3 );

function evw_update_check( $update, $plugin_data, $plugin_file ) {
	if ( EVW_BASENAME !== $plugin_file ) {
		return $update;
	}
	$rel = evw_latest_release();
	if ( ! $rel ) {
		return $update;
	}
	return array(
		'slug'    => 'evermonth-web',
		'version' => $rel['version'],
		'url'     => $rel['url'],
		'package' => $rel['package'],
	);
}

/** The "View details" window in Plugins. */
add_filter( 'plugins_api', 'evw_plugin_info', 10, 3 );

function evw_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'evermonth-web' !== $args->slug ) {
		return $result;
	}
	$rel = evw_latest_release();
	return (object) array(
		'name'          => 'Evermonth Web',
		'slug'          => 'evermonth-web',
		'version'       => $rel ? $rel['version'] : EVW_VERSION,
		'author'        => 'Made by Wacho',
		'homepage'      => 'https://github.com/' . EVW_REPO,
		'last_updated'  => $rel ? $rel['published'] : '',
		'download_link' => $rel ? $rel['package'] : '',
		'sections'      => array(
			'description' => 'Sitio nuevo de Campamento Evermonth dentro de WordPress.',
			'changelog'   => $rel ? nl2br( esc_html( $rel['notes'] ) ) : '',
		),
	);
}

/** After an update, forget the cached release. */
add_action(
	'upgrader_process_complete',
	function () {
		delete_site_transient( EVW_RELEASE_CACHE );
	}
);
