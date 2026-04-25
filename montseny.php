<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical modular v2.6.
Version: 2.6
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
require_once MONTSENY_PATH . 'includes/app-ui.php';
require_once MONTSENY_PATH . 'includes/gestion-ui.php';
