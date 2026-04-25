<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical y PWA. Versión 1.1 - Con Noticias y Telegram.
Version: 1.1
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 0. SISTEMA DE ACTUALIZACIÓN AUTOMÁTICA DESDE GITHUB
 * Importante: Cambia 'TU_USUARIO_GITHUB' por tu nombre de usuario real.
 */
add_filter( 'pre_set_site_transient_update_plugins', 'montseny_check_update' );

function montseny_check_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;

    $user = 'CNTFormacionCentro'; // <--- CAMBIA ESTO POR TU NOMBRE DE USUARIO DE GITHUB
    $repo = 'montseny';
    $url = "https://api.github.com/repos/$user/$repo/releases/latest";

    $response = wp_remote_get( $url, array( 'user-agent' => 'WordPress' ) );
    if ( is_wp_error( $response ) ) return $transient;

    $release = json_decode( wp_remote_retrieve_body( $response ) );

    if ( isset($release->tag_name) && version_compare( $release->tag_name, '1.1', '>' ) ) {
        $obj = new stdClass();
        $obj->slug = 'montseny/montseny.php';
        $obj->new_version = $release->tag_name;
        $obj->url = "https://github.com/$user/$repo";
        $obj->package = $release->zipball_url;
        $transient->response['montseny/montseny.php'] = $obj;
    }
    return $transient;
}

/**
 * 1. AL ACTIVAR: CREAR TABLAS Y ROLES
 */
register_activation_hook( __FILE__, 'montseny_instalar' );

function montseny_instalar() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $tabla_afiliados = $wpdb->prefix . 'montseny_afiliados';

    $sql = "CREATE TABLE $tabla_afiliados (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        dni_cifrado text,
        iban_cifrado text,
        telefono varchar(20),
        empresa varchar(150),
        sector varchar(100),
        trabajando tinyint(1) DEFAULT 1,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    if ( ! get_option( 'montseny_secret_key' ) ) {
        update_option( 'montseny_secret_key', wp_generate_password( 64, true, true ) );
    }

    add_role( 'tesorero_sindical', 'Tesorería Montseny', array( 'read' => true ) );
    add_role( 'afiliade', 'Afiliade Montseny', array( 'read' => true ) );
}

/**
 * 2. LÓGICA DE NOTICIAS (WEB + TELEGRAM)
 */
function montseny_get_feeds() {
    $feeds = array();

    // A. Noticias Locales (de Ciudad Real)
    $locales = get_posts( array( 'numberposts' => 2 ) );
    foreach ( $locales as $post ) {
        $feeds[] = array( 'fuente' => 'Local', 'titulo' => $post->post_title, 'link' => get_permalink($post->ID) );
    }

    // B. Noticias Confederales (cnt.es)
    $rss = fetch_feed( 'https://www.cnt.es/feed/' );
    if ( ! is_wp_error( $rss ) ) {
        $maxitems = $rss->get_item_quantity( 2 );
        $rss_items = $rss->get_items( 0, $maxitems );
        foreach ( $rss_items as $item ) {
            $feeds[] = array( 'fuente' => 'Confederal', 'titulo' => $item->get_title(), 'link' => $item->get_permalink() );
        }
    }

    // C. Telegram (vía scrap público sencillo)
    // Cambia 'cnt_ciudadreal' por el alias real del canal
    $telegram_user = 'cnt_nacional'; 
    $tg_response = wp_remote_get( "https://t.me/s/" . $telegram_user );
    if ( ! is_wp_error( $tg_response ) ) {
        $html = wp_remote_retrieve_body( $tg_response );
        // Buscamos el texto del último mensaje de forma rústica pero efectiva
        if ( preg_match_all( '/<div class="tgme_widget_message_text[^>]*>(.*?)<\/div>/s', $html, $matches ) ) {
            $ultimo_mensaje = end( $matches[1] );
            $feeds[] = array( 'fuente' => 'Telegram', 'titulo' => wp_trim_words(strip_tags($ultimo_mensaje), 15), 'link' => "https://t.me/s/" . $telegram_user );
        }
    }

    return $feeds;
}

/**
 * 3. INTERFAZ APP PWA
 */
add_action( 'init', function() {
    if ( strpos( $_SERVER['REQUEST_URI'], '/montseny' ) !== false ) {
        montseny_mostrar_app();
        exit;
    }
});

function montseny_mostrar_app() {
    $nombre_local = get_option('montseny_nombre_local', 'CNT');
    $noticias = montseny_get_feeds();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>App Montseny</title>
        <style>
            body { font-family: sans-serif; background: #000; color: #fff; margin: 0; padding-bottom: 60px; }
            .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; position: sticky; top: 0; z-index: 100; }
            .container { padding: 15px; }
            .card { background: #1a1a1a; border-left: 4px solid #CC0000; padding: 12px; margin-bottom: 12px; border-radius: 4px; }
            .card small { color: #CC0000; font-weight: bold; font-size: 0.7rem; text-transform: uppercase; }
            .card h4 { margin: 5px 0; font-size: 0.95rem; line-height: 1.3; }
            .card a { color: #fff; text-decoration: none; }
            .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="bar">MONTSENY - <?php echo esc_html($nombre_local); ?></div>
        <div class="container">
            <h3>Última Hora</h3>
            <?php foreach ( $noticias as $n ) : ?>
                <div class="card">
                    <small><?php echo $n['fuente']; ?></small>
                    <a href="<?php echo $n['link']; ?>" target="_blank"><h4><?php echo $n['titulo']; ?></h4></a>
                </div>
            <?php endforeach; ?>

            <?php if ( ! is_user_logged_in() ) : ?>
                <a href="<?php echo wp_login_url( site_url('/montseny') ); ?>" class="btn">ACCESO AFILIADOS</a>
            <?php else : ?>
                <div class="card" style="border-color: #00cc00;">
                    <small>ESTADO</small>
                    <h4>Salud, compa. Sesión activa.</h4>
                </div>
                <a href="<?php echo wp_logout_url( site_url('/montseny') ); ?>" style="color: #666; display: block; text-align: center; margin-top: 20px;">Cerrar Sesión</a>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

/**
 * 4. PANEL ADMIN WP
 */
add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_dash', 'dashicons-shield-alt', 2 );
});

function montseny_dash() {
    if ( isset($_POST['m_save']) ) {
        update_option('montseny_nombre_local', sanitize_text_field($_POST['l_name']));
        echo '<div class="updated"><p>Guardado.</p></div>';
    }
    $val = get_option('montseny_nombre_local', 'CNT Ciudad Real');
    ?>
    <div class="wrap">
        <h1>Configuración Montseny v1.1</h1>
        <form method="post">
            <input type="text" name="l_name" value="<?php echo esc_attr($val); ?>" placeholder="Nombre Local">
            <input type="submit" name="m_save" class="button button-primary" value="Actualizar">
        </form>
        <p>Tu App está en: <a href="<?php echo site_url('/montseny'); ?>" target="_blank"><?php echo site_url('/montseny'); ?></a></p>
    </div>
    <?php
}
