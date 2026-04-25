<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical y PWA. v2.0 - Alta con contraseña y Carnet con QR.
Version: 2.0
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 0. SISTEMA DE ACTUALIZACIÓN
 */
add_filter( 'pre_set_site_transient_update_plugins', 'montseny_check_update' );
function montseny_check_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;
    $user = 'CNTFormacionCentro'; $repo = 'montseny'; $plugin_slug = 'montseny/montseny.php'; 
    $url = "https://api.github.com/repos/$user/$repo/releases/latest";
    $response = wp_remote_get( $url, array( 'user-agent' => 'WordPress' ) );
    if ( is_wp_error( $response ) ) return $transient;
    $release = json_decode( wp_remote_retrieve_body( $response ) );
    $current_version = isset($transient->checked[$plugin_slug]) ? $transient->checked[$plugin_slug] : '1.9';
    if ( isset($release->tag_name) && version_compare( $release->tag_name, $current_version, '>' ) ) {
        $obj = new stdClass(); $obj->slug = $plugin_slug; $obj->new_version = $release->tag_name;
        $obj->url = "https://github.com/$user/$repo"; $obj->package = $release->zipball_url;
        $transient->response[$plugin_slug] = $obj;
    }
    return $transient;
}

add_filter('upgrader_source_selection', 'montseny_fix_folder_name', 10, 4);
function montseny_fix_folder_name($source, $remote_source, $upgrader, $hook_extra) {
    if (strpos($source, 'montseny') !== false) {
        $corrected_source = trailingslashit($remote_source) . 'montseny/';
        if (rename($source, $corrected_source)) return $corrected_source;
    }
    return $source;
}

/**
 * 1. SEGURIDAD Y CIFRADO
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
 * 2. PANEL ADMIN: ALTA MEJORADA CON CONTRASEÑA
 */
add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_dash', 'dashicons-shield-alt', 2 );
    add_submenu_page( 'montseny-dashboard', 'Lista Afiliados', 'Lista Afiliados', 'manage_options', 'montseny-lista', 'montseny_lista_afiliados' );
    add_submenu_page( 'montseny-dashboard', 'Añadir Afiliado', 'Añadir Afiliado', 'manage_options', 'montseny-alta', 'montseny_alta_afiliado' );
});

function montseny_alta_afiliado() {
    if ( isset($_POST['m_alta']) ) {
        $email = sanitize_email($_POST['email']);
        $pass = sanitize_text_field($_POST['password']);
        $user_id = wp_create_user($email, $pass, $email);
        
        if ( is_wp_error($user_id) ) { 
            echo '<div class="error"><p>'.$user_id->get_error_message().'</p></div>'; 
        } else {
            wp_update_user(array('ID' => $user_id, 'display_name' => sanitize_text_field($_POST['nombre'])));
            $u = new WP_User($user_id); $u->set_role('afiliade');
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'montseny_afiliados', array(
                'user_id' => $user_id,
                'dni_cifrado' => Montseny_Crypto::encrypt($_POST['dni']),
                'iban_cifrado' => Montseny_Crypto::encrypt($_POST['iban']),
                'genero' => $_POST['genero'],
                'telefono' => sanitize_text_field($_POST['tel']),
                'sector' => $_POST['sector'],
                'empresa' => $_POST['empresa']
            ));
            echo '<div class="updated"><p>Compa registrado con la contraseña indicada.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Nuevo Afiliado</h1>
        <form method="post" style="background:#fff; padding:20px; border:1px solid #ccd0d4; max-width:600px;">
            <h3>Acceso</h3>
            <input type="text" name="nombre" placeholder="Nombre completo" required class="regular-text"><br><br>
            <input type="email" name="email" placeholder="Email (Usuario)" required class="regular-text"><br><br>
            <input type="text" name="password" placeholder="Contraseña para el afiliado" required class="regular-text"><br>
            
            <h3>Datos</h3>
            <input type="text" name="dni" placeholder="DNI">
            <input type="text" name="iban" placeholder="IBAN" class="regular-text"><br><br>
            
            <select name="sector">
                <option value="Oficios Varios">Oficios Varios</option>
                <option value="Enseñanza">Enseñanza y Cultura</option>
                <option value="Metal">Metal y Química</option>
                <option value="Hostelería">Hostelería y Alimentación</option>
            </select>
            <input type="text" name="tel" placeholder="Teléfono">
            <input type="text" name="empresa" placeholder="Empresa"><br><br>
            
            <input type="submit" name="m_alta" class="button button-primary" value="Registrar">
        </form>
    </div>
    <?php
}

/**
 * 3. INTERFAZ APP PWA CON CÓDIGO QR
 */
add_action( 'init', function() {
    if ( strpos( $_SERVER['REQUEST_URI'], '/montseny' ) !== false ) {
        if (isset($_POST['montseny_login_submit'])) {
            $user = wp_signon(array('user_login'=>$_POST['log'],'user_password'=>$_POST['pwd'],'remember'=>true), false);
            wp_redirect(site_url(is_wp_error($user) ? '/montseny/?login=failed' : '/montseny/')); exit;
        }
        $nombre_local = get_option('montseny_nombre_local', 'CNT');
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Montseny</title>
            <style>
                body { font-family: sans-serif; background: #000; color: #fff; margin: 0; text-align: center; }
                .bar { background: #CC0000; padding: 20px; font-weight: bold; }
                .container { padding: 20px; }
                .carnet { background: #1a1a1a; border: 2px solid #CC0000; padding: 20px; border-radius: 15px; margin-bottom: 20px; text-align: left; }
                .qr-box { background: #fff; padding: 10px; display: inline-block; border-radius: 10px; margin-top: 15px; }
                input { width: 100%; padding: 12px; margin-bottom: 10px; border-radius: 8px; border: none; box-sizing: border-box; }
                .btn { background: #CC0000; color: #fff; padding: 15px; border-radius: 8px; font-weight: bold; border: none; width: 100%; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class="bar">MONTSENY - <?php echo esc_html($nombre_local); ?></div>
            <div class="container">
                <?php if ( is_user_logged_in() ) : 
                    global $wpdb;
                    $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}montseny_afiliados WHERE user_id = %d", get_current_user_id()));
                    $dni = Montseny_Crypto::decrypt($data->dni_cifrado);
                ?>
                    <div class="carnet">
                        <small style="color: #CC0000;">CARNET SINDICAL</small>
                        <h2 style="margin: 5px 0;"><?php echo wp_get_current_user()->display_name; ?></h2>
                        <p style="font-size: 0.9rem; opacity: 0.8;">
                            <?php echo esc_html($data->sector); ?><br>DNI: <?php echo $dni; ?>
                        </p>
                        <!-- Generador de QR automático usando una API libre -->
                        <div style="text-align: center;">
                            <div class="qr-box">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode("CNT:".$dni); ?>" alt="QR Carnet">
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" style="color: #666; text-decoration: none;">Cerrar Sesión</a>
                <?php else : ?>
                    <form method="post">
                        <h3>Acceso</h3>
                        <input type="text" name="log" placeholder="Email" required>
                        <input type="password" name="pwd" placeholder="Contraseña" required>
                        <input type="hidden" name="montseny_login_submit" value="1">
                        <button type="submit" class="btn">ENTRAR</button>
                    </form>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
});

// [Las funciones montseny_dash y montseny_lista_afiliados se mantienen igual]
