<?php
/**
 * SISTEMA DE ACTUALIZACIÓN AUTOMÁTICA DESDE GITHUB
 */
add_filter( 'pre_set_site_transient_update_plugins', 'montseny_check_update' );

function montseny_check_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;

    $user = 'formacion@centro.cnt.es'; // <--- CAMBIA ESTO
    $repo = 'montseny';
    $url = "https://api.github.com/repos/$user/$repo/releases/latest";

    // Pedir a GitHub la última versión lanzada
    $response = wp_remote_get( $url, array( 'user-agent' => 'WordPress' ) );
    if ( is_wp_error( $response ) ) return $transient;

    $release = json_decode( wp_remote_retrieve_body( $response ) );

    // Si la versión de GitHub es mayor que la instalada...
    if ( version_compare( $release->tag_name, '1.0', '>' ) ) {
        $obj = new stdClass();
        $obj->slug = 'montseny/montseny.php';
        $obj->new_version = $release->tag_name;
        $obj->url = "https://github.com/$user/$repo";
        $obj->package = $release->zipball_url;
        $transient->response['montseny/montseny.php'] = $obj;
    }

    return $transient;
}

/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical y PWA. Prototipo para Ciudad Real.
Version: 1.0
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. AL ACTIVAR: CREAR TABLAS Y CLAVE DE SEGURIDAD AUTOMÁTICA
 */
register_activation_hook( __FILE__, 'montseny_instalar' );

function montseny_instalar() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Crear tabla de afiliados
    $tabla_afiliados = $wpdb->prefix . 'montseny_afiliados';
    $sql = "CREATE TABLE $tabla_afiliados (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        dni_cifrado text,
        iban_cifrado text,
        telefono varchar(20),
        empresa varchar(150),
        sector varchar(100),
        trabajando tinyint(1) DEFAULT 1,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Generar clave de cifrado automática si no existe (para no tocar wp-config)
    if ( ! get_option( 'montseny_secret_key' ) ) {
        update_option( 'montseny_secret_key', wp_generate_password( 64, true, true ) );
    }

    // Crear roles
    add_role( 'tesorero_sindical', 'Tesorería Montseny', array( 'read' => true, 'edit_posts' => false ) );
    add_role( 'afiliade', 'Afiliade Montseny', array( 'read' => true ) );
}

/**
 * 2. INTERCEPTAR LA URL /montseny PARA MOSTRAR LA APP
 */
add_action( 'init', function() {
    if ( strpos( $_SERVER['REQUEST_URI'], '/montseny' ) !== false ) {
        // Si el usuario intenta entrar en la App...
        montseny_mostrar_app();
        exit;
    }
});

function montseny_mostrar_app() {
    $sindicato_nombre = get_option('montseny_nombre_local', 'CNT');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>App Montseny</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #000; color: #fff; margin: 0; }
            .app-bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; position: sticky; top: 0; }
            .container { padding: 15px; }
            .news-card { background: #1a1a1a; border-left: 4px solid #CC0000; padding: 15px; margin-bottom: 15px; border-radius: 4px; }
            .news-card h4 { margin: 0 0 10px 0; color: #CC0000; }
            .btn-login { background: #CC0000; color: white; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; margin-top: 20px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="app-bar">MONTSENY - <?php echo esc_html($sindicato_nombre); ?></div>
        <div class="container">
            <h3>Noticias Recientes</h3>
            
            <!-- Ejemplo de noticia local -->
            <div class="news-card">
                <h4>Local: Ciudad Real</h4>
                <p>Próxima asamblea ordinaria: viernes 26 en la sede.</p>
            </div>

            <!-- Aquí iría la carga de noticias de cnt.es -->
            <div class="news-card" style="opacity: 0.7;">
                <h4>Confederal (cnt.es)</h4>
                <p>Cargando últimas noticias de la Confederación...</p>
            </div>

            <hr style="border: 0; border-top: 1px solid #333; margin: 20px 0;">

            <?php if ( ! is_user_logged_in() ) : ?>
                <p>Para ver tu carnet y gestionar tus datos, identificaos:</p>
                <a href="<?php echo wp_login_url( site_url('/montseny') ); ?>" class="btn-login">Entrar a mi Sindicato</a>
            <?php else : ?>
                <div class="news-card">
                    <h4>¡Salud, compa!</h4>
                    <p>Ya estáis dentro del sistema. Pronto activaremos vuestro carnet digital aquí.</p>
                </div>
                <a href="<?php echo wp_logout_url( site_url('/montseny') ); ?>" style="color: #666; font-size: 0.8rem;">Cerrar sesión</a>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

/**
 * 3. PANEL DE CONTROL EN EL BACKEND (WP-ADMIN)
 */
add_action( 'admin_menu', 'montseny_crear_menu' );

function montseny_crear_menu() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_pantalla_dashboard', 'dashicons-shield-alt', 2 );
    add_submenu_page( 'montseny-dashboard', 'Afiliados', 'Afiliados', 'manage_options', 'montseny-afiliados', 'montseny_pantalla_afiliados' );
}

function montseny_pantalla_dashboard() {
    if ( isset($_POST['montseny_save_settings']) ) {
        update_option('montseny_nombre_local', sanitize_text_field($_POST['local_name']));
        echo '<div class="updated"><p>Configuración guardada.</p></div>';
    }

    $nombre = get_option('montseny_nombre_local', 'CNT Ciudad Real');
    ?>
    <div class="wrap">
        <h1>Panel Montseny - Prototipo</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Nombre del Sindicato (Local)</th>
                    <td><input type="text" name="local_name" value="<?php echo esc_attr($nombre); ?>" class="regular-text"></td>
                </tr>
            </table>
            <p><strong>Enlace para los afiliados:</strong> <code><?php echo site_url('/montseny'); ?></code></p>
            <input type="submit" name="montseny_save_settings" class="button button-primary" value="Guardar Cambios">
        </form>
    </div>
    <?php
}

function montseny_pantalla_afiliados() {
    ?>
    <div class="wrap">
        <h1>Gestión de Afiliación</h1>
        <p>Aquí aparecerá la lista de compas y el botón para exportar al banco.</p>
        <button class="button">Añadir nuevo afiliado (Próximamente)</button>
    </div>
    <?php
}