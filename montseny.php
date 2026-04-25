<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical modular v3.1 - Arreglo Error 404 y Enrutado mejorado.
Version: 3.1
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MONTSENY_PATH', plugin_dir_path( __FILE__ ) );

// Cargamos módulos base
require_once MONTSENY_PATH . 'includes/updater.php';
require_once MONTSENY_PATH . 'includes/database.php';
require_once MONTSENY_PATH . 'includes/security.php';
require_once MONTSENY_PATH . 'includes/roles.php';
require_once MONTSENY_PATH . 'includes/news.php';
require_once MONTSENY_PATH . 'includes/card-ui.php';
require_once MONTSENY_PATH . 'includes/app-ui.php';
require_once MONTSENY_PATH . 'includes/gestion-ui.php';

/**
 * EL ENRUTADOR: Evita el error 404 y decide qué pantalla mostrar
 */
add_action('template_redirect', function() {
    $request_uri = $_SERVER['REQUEST_URI'];

    // Si la URL contiene /montseny
    if ( strpos($request_uri, '/montseny') !== false ) {
        
        // 1. Forzamos a WordPress a dar un OK (Código 200) en lugar de 404
        status_header(200);
        global $wp_query;
        $wp_query->is_404 = false;

        // 2. Decidimos qué interfaz cargar
        if ( strpos($request_uri, '/montseny/gestion') !== false ) {
            // Zona Tesorería
            if ( !current_user_can('montseny_tesorero') && !current_user_can('manage_options') ) {
                wp_redirect(site_url('/montseny/')); 
                exit;
            }
            montseny_render_gestion();
        } else {
            // Zona Afiliado / Login
            montseny_render_app();
        }
        exit;
    }
});
