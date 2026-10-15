<?php
/**
 * Ajustes > Evermonth Web: the on/off switch, preview links and the update check.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'evw',
			'evw_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $in ) {
					return array(
						'live'  => empty( $in['live'] ) ? 0 : 1,
						'index' => empty( $in['index'] ) ? 0 : 1,
					);
				},
			)
		);
	}
);

// A page cache must forget the old pages when the switch changes.
add_action(
	'update_option_evw_settings',
	function () {
		do_action( 'litespeed_purge_all' );
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
);

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Evermonth Web', 'Evermonth Web', 'manage_options', 'evermonth-web', 'evw_admin_page' );
	}
);

add_filter(
	'plugin_action_links_' . EVW_BASENAME,
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=evermonth-web' ) ) . '">Ajustes</a>' );
		return $links;
	}
);

// "Buscar actualización": ask GitHub now, then let WordPress refresh its update list.
add_action(
	'admin_post_evw_check',
	function () {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( 'Sin permiso.' );
		}
		check_admin_referer( 'evw_check' );
		evw_latest_release( true );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'options-general.php?page=evermonth-web&checked=1' ) );
		exit;
	}
);

function evw_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = evw_settings();
	$rel     = evw_latest_release();
	$has     = is_readable( EVW_DIR . 'site/index.html' );
	$newer   = $rel && version_compare( $rel['version'], EVW_VERSION, '>' );
	$preview = add_query_arg( 'evermonth', 'preview', home_url( '/' ) );
	?>
	<div class="wrap">
		<h1>Evermonth Web</h1>

		<?php if ( ! $has ) : ?>
			<div class="notice notice-error"><p>Falta la carpeta <code>site/</code> del plugin. Instala el zip de la versión publicada en GitHub.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['checked'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Listo. Revisé GitHub.</p></div>
		<?php endif; ?>

		<h2>1. Ver antes de encender</h2>
		<p>Solo tú lo ves. Los visitantes siguen viendo el sitio anterior.</p>
		<p>
			<a class="button" target="_blank" href="<?php echo esc_url( $preview ); ?>">Ver inicio</a>
			<a class="button" target="_blank" href="<?php echo esc_url( add_query_arg( 'evermonth', 'preview', home_url( '/halloween/' ) ) ); ?>">Ver Halloween</a>
		</p>

		<h2>2. Encender</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'evw' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Sitio nuevo</th>
					<td><label><input type="checkbox" name="evw_settings[live]" value="1" <?php checked( $s['live'] ); ?>>
						En vivo para todos. Toma <code>/</code> y <code>/halloween/</code>, y manda <code>/halloween-evermonth/</code> a <code>/halloween/</code>.</label>
						<p class="description">Apágalo y vuelve el sitio anterior al instante. No se borra nada.</p></td>
				</tr>
				<tr>
					<th scope="row">Google</th>
					<td><label><input type="checkbox" name="evw_settings[index]" value="1" <?php checked( $s['index'] ); ?>>
						Dejar que Google indexe el sitio nuevo.</label>
						<p class="description">Solo cuenta con el sitio en vivo.</p></td>
				</tr>
			</table>
			<?php submit_button( 'Guardar' ); ?>
		</form>

		<h2>3. Actualizaciones</h2>
		<p>Versión instalada: <strong><?php echo esc_html( EVW_VERSION ); ?></strong>.
			Última en GitHub: <strong><?php echo $rel ? esc_html( $rel['version'] ) : 'sin respuesta'; ?></strong>.</p>
		<?php if ( $newer ) : ?>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">Hay una versión nueva: ir a Plugins y actualizar</a></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="evw_check">
			<?php wp_nonce_field( 'evw_check' ); ?>
			<?php submit_button( 'Buscar actualización', 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
