<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical y PWA. v1.6 - Corrección definitiva de desactivación y nombres de carpeta.
Version: 1.6
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 0. SISTEMA DE ACTUALIZACIÓN AUTOMÁTICA MEJORADO
 */
add_filter( 'pre_set_site_transient_update_plugins', 'montseny_check_update' );
function montseny_check_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;
    $user = 'CNTFormacionCentro'; 
    $repo = 'montseny';
    $plugin_slug = plugin_basename(__FILE__); 
    $url = "https://api.github.com/repos/$user/$repo/releases/latest";
    $response = wp_remote_get( $url, array( 'user-agent' => 'WordPress' ) );
    if ( is_wp_error( $response ) ) return $transient;
    $release = json_decode( wp_remote_retrieve_body( $response ) );
    $current_version = $transient->checked[$plugin_slug];
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
 * FILTRO CRÍTICO: Renombrar la carpeta de GitHub a 'montseny' automáticamente
 * Esto evita que el plugin se desactive al actualizar.
 */
add_filter('upgrader_source_selection', 'montseny_fix_github_folder', 10, 4);
function montseny_fix_github_folder($source, $remote_source, $upgrader, $hook_extra) {
    if (strpos($source, 'montseny') !== false) {
        $corrected_source = trailingslashit($remote_source) . 'montseny/';
        if (rename($source, $corrected_source)) {
            return $corrected_source;
        }
    }
    return $source;
}

/**
 * 1. SEGURIDAD: CIFRADO
 */
class Montseny_Crypto {
    private static $method = 'aes-256-cbc';
    public static function encrypt($data) {
        if(empty($data)) return '';
        $key = get_option('montseny_secret_key');
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$method));
        $encrypted = openssl_encrypt($data, self::$method, $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    public static function decrypt($data) {
        if(empty($data)) return '';
        $key = get_option('montseny_secret_key');
        $parts = explode('::', base64_decode($data), 2);
        if(count($parts) < 2) return '---';
        list($encrypted_data, $iv) = $parts;
        return openssl_decrypt($encrypted_data, self::$method, $key, 0, $iv);
    }
}

/**
 * 2. PANEL ADMIN: GESTIÓN DE AFILIADOS
 */
add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_dash', 'dashicons-shield-alt', 2 );
    add_submenu_page( 'montseny-dashboard', 'Lista Afiliados', 'Lista Afiliados', 'manage_options', 'montseny-lista', 'montseny_lista_afiliados' );
    add_submenu_page( 'montseny-dashboard', 'Añadir Afiliado', 'Añadir Afiliado', 'manage_options', 'montseny-alta', 'montseny_alta_afiliado' );
});

function montseny_lista_afiliados() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $resultados = $wpdb->get_results("SELECT * FROM $tabla");
    echo '<div class="wrap"><h1>Lista de Afiliación</h1><table class="wp-list-table widefat fixed striped"><thead><tr><th>Nombre</th><th>Email</th><th>DNI</th><th>Ramo</th><th>Empresa</th></tr></thead><tbody>';
    foreach ($resultados as $row) {
        $u = get_userdata($row->user_id);
        echo "<tr><td><strong>{$u->display_name}</strong></td><td>{$u->user_email}</td><td>".Montseny_Crypto::decrypt($row->dni_cifrado)."</td><td>{$row->sector}</td><td>{$row->empresa}</td></tr>";
    }
    echo '</tbody></table></div>';
}

function montseny_alta_afiliado() {
    if ( isset($_POST['m_alta']) ) {
        $email = sanitize_email($_POST['email']);
        $user_id = wp_create_user($email, wp_generate_password(12), $email);
        if ( is_wp_error($user_id) ) { echo '<div class="error"><p>'.$user_id->get_error_message().'</p></div>'; }
        else {
            wp_update_user(array('ID' => $user_id, 'display_name' => sanitize_text_field($_POST['nombre'])));
            $u = new WP_User($user_id); $u->set_role('afiliade');
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'montseny_afiliados', array(
                'user_id' => $user_id,
                'dni_cifrado' => Montseny_Crypto::encrypt($_POST['dni']),
                'iban_cifrado' => Montseny_Crypto::encrypt($_POST['iban']),
                'sector' => $_POST['sector'],
                'empresa' => $_POST['empresa']
            ));
            echo '<div class="updated"><p>Compa registrado correctamente.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Nuevo Registro Sindical</h1>
        <form method="post" style="background:#fff; padding:20px; border:1px solid #ccd0d4; max-width:600px;">
            <input type="text" name="nombre" placeholder="Nombre completo" required class="regular-text"><br><br>
            <input type="email" name="email" placeholder="Email" required class="regular-text"><br><br>
            <input type="text" name="dni" placeholder="DNI" required>
            <input type="text" name="iban" placeholder="IBAN" required class="regular-text"><br><br>
            <label>Ramo CNT:</label><br>
            <select name="sector" style="width:100%;">
                <option value="Oficios Varios">Oficios Varios</option>
                <option value="Metal, Minería y Química">Metal, Minería y Química</option>
                <option value="Sanidad, Acción Social, Enseñanza y Cultura">Sanidad, Acción Social, Enseñanza y Cultura</option>
                <option value="Comercio, Hostelería y Alimentación">Comercio, Hostelería y Alimentación</option>
                <option value="Administración y Servicios Públicos">Administración y Servicios Públicos</option>
            </select><br><br>
            <input type="text" name="empresa" placeholder="Empresa" class="regular-text"><br><br>
            <input type="submit" name="m_alta" class="button button-primary" value="Registrar Afiliado">
        </form>
    </div>
    <?php
}

/**
 * 3. INTERFAZ APP PWA
 */
add_action( 'init', function() {
    if ( strpos( $_SERVER['REQUEST_URI'], '/montseny' ) !== false ) {
        $nombre_local = get_option('montseny_nombre_local', 'CNT');
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Montseny</title>
            <style>
                body { font-family: sans-serif; background: #000; color: #fff; margin: 0; padding-bottom: 60px; }
                .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; position: sticky; top: 0; }
                .container { padding: 15px; }
                .carnet { background: linear-gradient(135deg, #222 0%, #000 100%); border: 2px solid #CC0000; padding: 20px; border-radius: 15px; }
                .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="bar">MONTSENY - <?php echo esc_html($nombre_local); ?></div>
            <div class="container">
                <?php if ( is_user_logged_in() ) : 
                    global $wpdb;
                    $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}montseny_afiliados WHERE user_id = %d", get_current_user_id()));
                ?>
                    <div class="carnet">
                        <small style="color: #CC0000;">CARNET SINDICAL</small>
                        <h2><?php echo wp_get_current_user()->display_name; ?></h2>
                        <p><?php echo esc_html($data->sector); ?><br>DNI: <?php echo Montseny_Crypto::decrypt($data->dni_cifrado); ?></p>
                    </div>
                    <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" style="color: #444; display: block; text-align: center; margin-top: 30px;">Cerrar Sesión</a>
                <?php else : ?>
                    <a href="<?php echo wp_login_url(site_url('/montseny')); ?>" class="btn">ENTRAR</a>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
});

function montseny_dash() {
    $val = get_option('montseny_nombre_local', 'CNT Ciudad Real');
    if (isset($_POST['m_save'])) {
        update_option('montseny_nombre_local', sanitize_text_field($_POST['l_name']));
        $val = $_POST['l_name'];
    }
    echo '<div class="wrap"><h1>Panel Montseny</h1><form method="post"><input type="text" name="l_name" value="'.esc_attr($val).'"><input type="submit" name="m_save" class="button button-primary" value="Guardar"></form></div>';
}
