<?php
/**
 * Router: answers the new-site URLs before WordPress looks for a page.
 * Every other URL (wp-admin, wp-json, old pages, email) stays with WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Path (no slashes) => file in site/. '' is the home page. */
function evw_pages() {
	return array(
		''          => 'index.html',
		'halloween' => 'halloween.html',
		'nosotros'  => 'nosotros.html',
	);
}

/** Old or file-style paths => path of the new page. They answer with a 301. */
function evw_aliases( $live ) {
	$a = array(
		'index.html'       => '',
		'halloween.html'   => 'halloween',
		'halloween-m.html' => 'halloween',
		'halloween-m'      => 'halloween',
		'nosotros.html'    => 'nosotros',
	);
	// The client's old Halloween page. Only when the new site is live, so the preview never hides it.
	if ( $live ) {
		$a['halloween-evermonth'] = 'halloween';
	}
	return $a;
}

const EVW_COOKIE = 'evw_preview';

/**
 * Preview: an admin opens any page with ?evermonth=preview to see the new site while it is off.
 * ?evermonth=off ends the preview. Visitors never see it.
 */
function evw_preview_on() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	if ( isset( $_GET['evermonth'] ) ) {
		$on = 'preview' === sanitize_key( wp_unslash( $_GET['evermonth'] ) );
		setcookie( EVW_COOKIE, $on ? '1' : '', $on ? 0 : time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		return $on;
	}
	return ! empty( $_COOKIE[ EVW_COOKIE ] );
}

add_action( 'parse_request', 'evw_route', 1 );

function evw_route( $wp ) {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return;
	}

	$live    = (bool) evw_settings()['live'];
	$preview = ! $live && evw_preview_on();
	if ( ! $live && ! $preview ) {
		return;
	}

	$path    = trim( (string) $wp->request, '/' );
	$aliases = evw_aliases( $live );
	if ( isset( $aliases[ $path ] ) ) {
		evw_redirect( '' === $aliases[ $path ] ? '' : $aliases[ $path ] . '/' );
	}

	$pages = evw_pages();
	if ( ! array_key_exists( $path, $pages ) ) {
		return;
	}
	// The home URL with a WordPress query (?s=, ?p=, ?page_id=, feeds) stays with WordPress.
	if ( '' === $path && ! empty( $wp->query_vars ) ) {
		return;
	}

	// http:// or www. -> the one https address. WordPress does this in redirect_canonical,
	// but we answer before it runs, so we do it here.
	$home = wp_parse_url( home_url() );
	$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$want = strtolower( $home['host'] ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
	$https = is_ssl()
		|| ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) )
		|| ( isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) && 'on' === strtolower( $_SERVER['HTTP_X_FORWARDED_SSL'] ) );
	if ( ( '' !== $host && $host !== $want ) || ( 'https' === $home['scheme'] && ! $https ) ) {
		evw_redirect( '' === $path ? '' : $path . '/' );
	}

	evw_serve( $pages[ $path ], $live, 'HEAD' === $method );
}

/** 301 to a path on the home URL. Keeps the query string (utm_*, fbclid from ads). */
function evw_redirect( $rel ) {
	$to = home_url( '/' . $rel );
	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		$to .= '?' . wp_unslash( $_SERVER['QUERY_STRING'] );
	}
	wp_safe_redirect( $to, 301, 'Evermonth Web' );
	exit;
}

/** Send one page of the new site and stop WordPress. */
function evw_serve( $file, $live, $head_only ) {
	$html = @file_get_contents( EVW_DIR . 'site/' . $file );
	if ( false === $html ) {
		return; // site/ is missing: let WordPress answer as before.
	}

	$html = evw_rewrite( $html, $live && evw_settings()['index'] );

	if ( ! $live ) {
		$html = str_replace( '</body>', evw_preview_badge() . '</body>', $html );
	}

	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	// Security headers. WordPress's own send_headers never runs here (we exit in parse_request).
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
	if ( $live ) {
		header( 'Cache-Control: public, max-age=300' );
	} else {
		nocache_headers();
	}
	if ( ! $head_only ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- our own static page.
	}
	exit;
}

/**
 * Fix the links of the static page for this WordPress:
 * - assets (img/, css/, js/, video/, fuentes/) point to the plugin folder,
 * - page links point to the pretty URLs (/, /halloween/ and /nosotros/),
 * - the test-link URLs (canonical, og:, JSON-LD) point to this site,
 * - data-base on <html> tells the JS where the images are.
 */
function evw_rewrite( $html, $allow_index ) {
	$base = plugins_url( 'site/', EVW_FILE );
	$home = home_url( '/' );
	$test = 'https://samples.madebywacho.com/evermonth-010/';
	$dirs = '(img|css|js|video|fuentes)/';

	$html = preg_replace( '#' . preg_quote( $test, '#' ) . $dirs . '#', $base . '$1/', $html );
	$html = preg_replace( '#\b(src|href|poster)="' . $dirs . '#', '$1="' . $base . '$2/', $html );
	// srcset / imagesrcset hold a list of URLs: fix each one.
	$html = preg_replace_callback(
		'#\b((?:image)?srcset)="([^"]*)"#',
		function ( $m ) use ( $base, $dirs ) {
			return $m[1] . '="' . preg_replace( '#(^|,\s*)' . $dirs . '#', '$1' . $base . '$2/', $m[2] ) . '"';
		},
		$html
	);

	$html = str_replace(
		array( $test . 'halloween.html', $test . 'halloween', $test . 'nosotros.html', $test . 'nosotros', $test . 'index.html', $test ),
		array( $home . 'halloween/', $home . 'halloween/', $home . 'nosotros/', $home . 'nosotros/', $home, $home ),
		$html
	);
	$html = preg_replace( '#href="halloween(-m)?\.html#', 'href="' . $home . 'halloween/', $html );
	$html = preg_replace( '#href="nosotros\.html#', 'href="' . $home . 'nosotros/', $html );
	$html = preg_replace( '#href="index\.html#', 'href="' . $home, $html );

	$html = preg_replace( '#<html\b#', '<html data-base="' . esc_attr( $base ) . '"', $html, 1 );

	if ( $allow_index ) {
		$html = preg_replace( '#\s*<meta\s+name="robots"\s+content="[^"]*noindex[^"]*"\s*/?>#i', '', $html );
	}
	return $html;
}

function evw_preview_badge() {
	$off = esc_url( add_query_arg( 'evermonth', 'off' ) );
	return '<div style="position:fixed;left:12px;bottom:12px;z-index:2147483647;font:600 13px/1.3 system-ui,sans-serif;'
		. 'background:#16130C;color:#E6DAB4;padding:8px 12px;border:2px solid #E8A720;border-radius:8px">'
		. 'Vista previa · solo tú la ves · <a href="' . $off . '" style="color:#E8A720">salir</a></div>';
}
