<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lógica para servir el Manifiesto y el Service Worker
 */
add_action('init', function() {
    $request = $_SERVER['REQUEST_URI'];

    // 1. Servir el Manifiesto
    if ( strpos($request, '/montseny/manifest.json') !== false ) {
        header('Content-Type: application/json; charset=utf-8');
        $local = get_option('montseny_nombre_local', 'CNT');
        $manifest = [
            "name" => "Montseny - $local",
            "short_name" => "Montseny",
            "start_url" => site_url('/montseny/'),
            "display" => "standalone",
            "background_color" => "#000000",
            "theme_color" => "#CC0000",
            "icons" => [
                [
                    "src" => "https://www.cnt.es/wp-content/uploads/2017/10/logo-cnt.png", // Icono provisional
                    "sizes" => "192x192",
                    "type" => "image/png"
                ]
            ]
        ];
        echo json_encode($manifest);
        exit;
    }

    // 2. Servir el Service Worker (Mínimo para que sea instalable)
    if ( strpos($request, '/montseny/sw.js') !== false ) {
        header('Content-Type: application/javascript; charset=utf-8');
        echo "self.addEventListener('fetch', function(event) {});";
        exit;
    }
});
