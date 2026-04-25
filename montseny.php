<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical y PWA. Versión 1.1 - Con Noticias Reales y Telegram.
Version: 1.1
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 0. SISTEMA DE ACTUALIZACIÓN AUTOMÁTICA DESDE GITHUB
 */
add_filter( 'pre_set_site_transient_update_plugins', 'montseny_check_update' );

function montseny_check_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;

    $user = 'CNTFormacionCentro'; 
    $repo = 'montseny';
    $plugin_slug = 'montseny/montseny.php'; // Carpeta/archivo.php

    $url = "https://api.github.com/repos/$user/$repo/releases/latest";
    $response = wp_remote_get( $url, array( 'user-agent' => 'WordPress' ) );

    if ( is_wp_error( $response ) ) return $transient;

    $release = json_decode( wp_remote_retrieve_body( $response ) );
    
    // Obtenemos la versión instalada actualmente
    $current_version = $transient->checked[$plugin_slug];

    // Si hay una versión en GitHub y es superior a la instalada
    if ( isset($release->tag_name) && version_compare( $release->tag_name, $current_version, '>' ) ) {
        $obj = new stdClass();
        $obj->slug = $plugin_slug;
        $obj->new_version = $release->tag_name;
        $obj->url = "https://github.com/$user/$repo";
        $obj->package = $release->zipball_url;
        $transient->response[$plugin_slug] = $obj;
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

    // Añadir roles si no existen
    if ( ! get_role( 'tesorero_sindical' ) ) {
        add_role( 'tesorero_sindical', 'Tesorería Montseny', array( 'read' => true ) );
    }
    if ( ! get_role( 'afiliade' ) ) {
        add_role( 'afiliade', 'Afiliade Montseny', array( 'read' => true ) );
    }
}

/**
 * 2. LÓGICA DE NOTICIAS (WEB LOCAL + CNT.ES + TELEGRAM)
 */
function montseny_get_feeds() {
    $feeds = array();

    // A. Noticias Locales (de Ciudad Real)
    $locales = get_posts( array( 'numberposts' => 2 ) );
    foreach ( $locales as $post ) {
        $feeds[] = array( 
            'fuente' => 'Local', 
            'titulo' => $post->post_title, 
            'link' => get_permalink($post->ID) 
        );
    }

    // B. Noticias Confederales (cnt.es)
    include_once( ABSPATH . WPINC . '/feed.php' );
    $rss = fetch_feed( 'https://www.cnt.es/feed/' );
    if ( ! is_wp_error( $rss ) ) {
        $maxitems = $rss->get_item_quantity( 2 );
        $rss_items = $rss->get_items( 0, $maxitems );
        foreach ( $rss_items as $item ) {
            $feeds[] = array( 
                'fuente' => 'Confederal', 
                'titulo' => $item->get_title(), 
                'link' => $item->get_permalink() 
            );
        }
    }

    // C. Telegram (vía scrap público del canal nacional)
    $telegram_user = 'cnt_nacional'; 
    $tg_response = wp_remote_get( "https://t.me/s/" . $telegram_user );
    if ( ! is_wp_error( $tg_response ) ) {
        $html = wp_remote_retrieve_body( $tg_response );
        if ( preg_match_all( '/<div class="tgme_widget_message_text[^>]*>(.*?)<\/div>/s', $html, $matches ) ) {
            $ultimo_mensaje = end( $matches[1] );
            $feeds[] = array( 
                'fuente' => 'Telegram', 
                'titulo' => wp_trim_words(strip_tags($ultimo_mensaje), 15), 
                'link' => "https://t.me/s/" . $telegram_user 
            );
        }
    }

    return $feeds;
}

/**
 * 3. INTERFAZ APP PWA (URL /montseny)
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
            .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.5); }
            .container { padding: 15px; }
            .card { background: #1a1a1a; border-left: 4px solid #CC0000; padding: 12px; margin-bottom: 12px; border-radius: 4px; }
            .card small { color: #CC0000; font-weight: bold; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; }
            .card h4 { margin: 5px 0; font-size: 0.95rem; line-height: 1.3; }
            .card a { color: #fff; text-decoration: none; }
            .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 20px; }
            .footer-info { color: #444; text-align: center; font-size: 0.7rem; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class="bar">MONTSENY - <?php echo esc_html($nombre_local); ?></div>
        <div class="container">
            <h3>Última Hora</h3>
            
            <?php if ( empty($noticias) ) : ?>
                <p>No hay noticias recientes.</p>
            <?php else : ?>
                <?php foreach ( $noticias as $n ) : ?>
                    <div class="card">
                        <small><?php echo esc_html($n['fuente']); ?></small>
                        <a href="<?php echo esc_url($n['link']); ?>" target="_blank">
                            <h4><?php echo esc_html($n['titulo']); ?></h4>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div style="margin-top: 30px;">
                <?php if ( ! is_user_logged_in() ) : ?>
                    <a href="<?php echo wp_login_url( site_url('/montseny') ); ?>" class="btn">ACCESO AFILIADOS</a>
                <?php else : ?>
                    <div class="card" style="border-color: #00cc00;">
                        <small>ESTADO</small>
                        <h4>Salud, compa. Sesión activa.</h4>
                    </div>
                    <a href="<?php echo wp_logout_url( site_url('/montseny') ); ?>" style="color: #666; display: block; text-align: center; margin-top: 20px; text-decoration: none; font-size: 0.9rem;">Cerrar Sesión</a>
                <?php endif; ?>
            </div>

            <div class="footer-info">Montseny Project - Herramienta Libre</div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * 4. PANEL DE ADMINISTRACIÓN (BACKEND)
 */
add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_dash', 'dashicons-shield-alt', 2 );
});

function montseny_dash() {
    if ( isset($_POST['m_save']) ) {
        update_option('montseny_nombre_local', sanitize_text_field($_POST['l_name']));
        echo '<div class="updated"><p>Configuración actualizada correctamente.</p></div>';
    }
    $val = get_option('montseny_nombre_local', 'CNT Ciudad Real');
    ?>
    <div class="wrap">
        <h1>Configuración Montseny v1.1</h1>
        <div class="card" style="max-width: 500px; padding: 20px;">
            <form method="post">
                <p>
                    <label><strong>Nombre de la Local:</strong></label><br>
                    <input type="text" name="l_name" value="<?php echo esc_attr($val); ?>" class="regular-text" placeholder="Ej: CNT Ciudad Real">
                </p>
                <p>
                    <input type="submit" name="m_save" class="button button-primary" value="Guardar Cambios">
                </p>
            </form>
        </div>
        <p>Enlace de la App para afiliados: <a href="<?php echo site_url('/montseny'); ?>" target="_blank"><?php echo site_url('/montseny'); ?></a></p>
    </div>
    <?php
}
