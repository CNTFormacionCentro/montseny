<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical modular v3.7 - Ahora con modo APP instalable (PWA).
Version: 3.7
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
require_once MONTSENY_PATH . 'includes/pwa.php';

add_action('template_redirect', function() {
    if ( strpos($_SERVER['REQUEST_URI'], '/montseny') !== false ) {
        status_header(200);
        global $wp_query; $wp_query->is_404 = false;
        if ( strpos($_SERVER['REQUEST_URI'], '/montseny/gestion') !== false ) {
            if ( !current_user_can('montseny_tesorero') && !current_user_can('manage_options') ) {
                wp_redirect(site_url('/montseny/')); exit;
            }
            montseny_render_gestion();
        } else {
            montseny_render_app();
        }
        exit;
    }
});
