<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SISTEMA DE ACTUALIZACIÓN DESDE GITHUB
 */
add_filter( 'pre_set_site_transient_update_plugins', 'montseny_check_update' );
function montseny_check_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;

    $user = 'CNTFormacionCentro'; // <--- TU USUARIO DE GITHUB
    $repo = 'montseny';
    // El slug debe apuntar al archivo principal fuera de includes/
    $plugin_slug = 'montseny/montseny.php'; 

    $url = "https://api.github.com/repos/$user/$repo/releases/latest";
    $response = wp_remote_get( $url, array( 'user-agent' => 'WordPress' ) );

    if ( is_wp_error( $response ) ) return $transient;

    $release = json_decode( wp_remote_retrieve_body( $response ) );
    $current_version = isset($transient->checked[$plugin_slug]) ? $transient->checked[$plugin_slug] : '2.5';

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
 * RENOMBRADO DE CARPETA (Para evitar que se desactive)
 */
add_filter('upgrader_source_selection', 'montseny_rename_github_folder', 10, 4);
function montseny_rename_github_folder($source, $remote_source, $upgrader, $hook_extra) {
    if (strpos($source, 'montseny') !== false) {
        $corrected_source = trailingslashit($remote_source) . 'montseny/';
        if (rename($source, $corrected_source)) {
            return $corrected_source;
        }
    }
    return $source;
}
