<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical v4.0 - MODO APP ACTIVADO (PWA).
Version: 4.0
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MONTSENY_PATH', plugin_dir_path( __FILE__ ) );

// Cargamos todos los módulos
require_once MONTSENY_PATH . 'includes/updater.php';
require_once MONTSENY_PATH . 'includes/database.php';
require_once MONTSENY_PATH . 'includes/security.php';
require_once MONTSENY_PATH . 'includes/roles.php';
require_once MONTSENY_PATH . 'includes/news.php';
require_once MONTSENY_PATH . 'includes/card-ui.php';
require_once MONTSENY_PATH . 'includes/app-ui.php';
require_once MONTSENY_PATH . 'includes/gestion-ui.php';

/**
 * ENRUTADOR MONTSENY v4.0
 */
add_action('template_redirect', function() {
    $url = $_SERVER['REQUEST_URI'];

    if ( strpos($url, '/montseny') !== false ) {
        status_header(200);
        global $wp_query; $wp_query->is_404 = false;

        // 1. Entregar el Manifiesto (DNI de la App)
        if ( strpos($url, 'manifest.json') !== false ) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                "name" => "Montseny - CNT",
                "short_name" => "Montseny",
                "start_url" => site_url('/montseny/'),
                "display" => "standalone",
                "background_color" => "#000000",
                "theme_color" => "#CC0000",
                "icons" => [[
                    "src" => "https://www.cnt.es/wp-content/uploads/2017/10/logo-cnt.png",
                    "sizes" => "192x192",
                    "type" => "image/png",
                    "purpose" => "any maskable"
                ]]
            ]);
            exit;
        }

        // 2. Entregar el Service Worker (Motor de la App)
        if ( strpos($url, 'sw.js') !== false ) {
            header('Content-Type: application/javascript; charset=utf-8');
            echo "self.addEventListener('fetch', (event) => {});"; 
            exit;
        }

        // 3. Zona de Gestión
        if ( strpos($url, '/montseny/gestion') !== false ) {
            if ( !current_user_can('montseny_tesorero') && !current_user_can('montseny_comunica') && !current_user_can('manage_options') ) {
                wp_redirect(site_url('/montseny/')); exit;
            }
            montseny_render_gestion();
            exit;
        }

        // 4. App Principal
        montseny_render_app();
        exit;
    }
});
