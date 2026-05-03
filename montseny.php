<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical modular v4.0 - Modo APP (PWA) con archivos físicos.
Version: 4.0
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;
define( 'MONTSENY_PATH', plugin_dir_path( __FILE__ ) );

require_once MONTSENY_PATH . 'includes/updater.php';
require_once MONTSENY_PATH . 'includes/database.php';
require_once MONTSENY_PATH . 'includes/security.php';
require_once MONTSENY_PATH . 'includes/roles.php';
require_once MONTSENY_PATH . 'includes/news.php';
require_once MONTSENY_PATH . 'includes/card-ui.php';
require_once MONTSENY_PATH . 'includes/app-ui.php';
require_once MONTSENY_PATH . 'includes/gestion-ui.php';

/**
 * SERVIDOR DE ARCHIVOS PWA
 * Evita el 404 entregando los archivos físicos de la carpeta del plugin
 */
add_action('init', function() {
    $url = $_SERVER['REQUEST_URI'];

    if ( strpos($url, 'montseny-manifest.json') !== false ) {
        header('Content-Type: application/json; charset=utf-8');
        readfile( MONTSENY_PATH . 'montseny.json' );
        exit;
    }

    if ( strpos($url, 'montseny-sw.js') !== false ) {
        header('Content-Type: application/javascript; charset=utf-8');
        readfile( MONTSENY_PATH . 'montseny-sw.js' );
        exit;
    }
});

/**
 * ENRUTADOR DE INTERFAZ
 */
add_action('template_redirect', function() {
    $url = $_SERVER['REQUEST_URI'];
    if ( strpos($url, '/montseny') !== false && !strpos($url, '.json') && !strpos($url, '.js') ) {
        status_header(200);
        global $wp_query; $wp_query->is_404 = false;

        if ( strpos($url, '/montseny/gestion') !== false ) {
            if (!current_user_can('montseny_tesorero') && !current_user_can('manage_options')) {
                wp_redirect(site_url('/montseny/')); exit;
            }
            montseny_render_gestion();
        } else {
            montseny_render_app();
        }
        exit;
    }
});
