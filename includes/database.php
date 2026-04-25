<?php
if ( ! defined( 'ABSPATH' ) ) exit;

register_activation_hook( MONTSENY_PATH . 'montseny.php', 'montseny_crear_tablas' );

function montseny_crear_tablas() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    
    $sql = "CREATE TABLE $tabla (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        genero varchar(20),
        fecha_nacimiento varchar(50),
        telefono varchar(20),
        direccion_cifrada text,
        iban_cifrado text,
        ramo varchar(100),
        etiquetas text,
        observaciones text,
        fecha_alta date,
        codigo_interno varchar(50),
        PRIMARY KEY  (id)
    ) {$wpdb->get_charset_collate()};";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    if ( ! get_option( 'montseny_secret_key' ) ) {
        update_option( 'montseny_secret_key', wp_generate_password( 64, true, true ) );
    }
}
