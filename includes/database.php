<?php
if ( ! defined( 'ABSPATH' ) ) exit;

register_activation_hook( MONTSENY_PATH . 'montseny.php', 'montseny_crear_tablas' );

function montseny_crear_tablas() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $tabla (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        situacion varchar(10) DEFAULT 'Alta',
        codigo_interno varchar(50) DEFAULT '',
        genero varchar(20) DEFAULT '',
        fecha_nacimiento varchar(50) DEFAULT '',
        telefono varchar(20) DEFAULT '',
        direccion_cifrada text,
        iban_cifrado text,
        ramo varchar(100) DEFAULT '',
        etiquetas text,
        observaciones text,
        fecha_alta date,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    if ( ! get_option( 'montseny_secret_key' ) ) {
        update_option( 'montseny_secret_key', wp_generate_password( 64, true, true ) );
    }
}
