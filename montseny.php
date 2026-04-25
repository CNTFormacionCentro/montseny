<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical modular v2.6.
Version: 2.6
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Definimos la ruta del plugin para que los otros archivos no se pierdan
define( 'MONTSENY_PATH', plugin_dir_path( __FILE__ ) );

// Llamamos a los "cajones" (archivos) que vamos a crear
require_once MONTSENY_PATH . 'includes/updater.php';
require_once MONTSENY_PATH . 'includes/database.php';
require_once MONTSENY_PATH . 'includes/roles.php';
// (Iremos añadiendo el resto conforme los creemos)
