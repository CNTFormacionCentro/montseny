<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical y PWA. v1.4 - Listado de Afiliados y Carnet en App.
Version: 1.4
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 0. SISTEMA DE ACTUALIZACIÓN AUTOMÁTICA
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

// Pantalla: Lista de Afiliados
function montseny_lista_afiliados() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $resultados = $wpdb->get_results("SELECT * FROM $tabla");
    ?>
    <div class="wrap">
        <h1>Afiliación Registrada</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>DNI</th>
                    <th>Sector</th>
                    <th>Empresa</th>
                    <th>IBAN</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $row) : 
                    $user_info = get_userdata($row->user_id);
                ?>
                <tr>
                    <td><strong><?php echo esc_html($user_info->display_name); ?></strong></td>
                    <td><?php echo esc_html($user_info->user_email); ?></td>
                    <td><code><?php echo esc_html(Montseny_Crypto::decrypt($row->dni_cifrado)); ?></code></td>
                    <td><?php echo esc_html($row->sector); ?></td>
                    <td><?php echo esc_html($row->empresa); ?></td>
                    <td><code><?php echo esc_html(Montseny_Crypto::decrypt($row->iban_cifrado)); ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Pantalla: Alta de Afiliado (Se mantiene igual que v1.3)
function montseny_alta_afiliado() {
    if ( isset($_POST['m_alta']) ) {
        $email = sanitize_email($_POST['email']);
        $nombre = sanitize_text_field($_POST['nombre']);
        $pass = wp_generate_password(12);
        $user_id = wp_create_user($email, $pass, $email);
        if ( is_wp_error($user_id) ) {
            echo '<div class="error"><p>' . $user_id->get_error_message() . '</p></div>';
        } else {
            wp_update_user(array('ID' => $user_id, 'display_name' => $nombre));
            $u = new WP_User($user_id); $u->set_role('afiliade');
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'montseny_afiliados', array(
                'user_id' => $user_id,
                'dni_cifrado' => Montseny_Crypto::encrypt($_POST['dni']),
                'iban_cifrado' => Montseny_Crypto::encrypt($_POST['iban']),
                'genero' => $_POST['genero'],
                'fecha_nacimiento' => $_POST['f_nac'],
                'telefono' => $_POST['tel'],
                'direccion_postal' => $_POST['dir'],
                'trabajando' => isset($_POST['trabaja']) ? 1 : 0,
                'empresa' => $_POST['empresa'],
                'sector' => $_POST['sector']
            ));
            echo '<div class="updated"><p>¡Afiliado registrado!</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Nuevo Registro</h1>
        <form method="post" style="background:#fff; padding:20px; border:1px solid #ccd0d4; max-width:600px;">
            <input type="text" name="nombre" placeholder="Nombre completo" required class="regular-text"><br><br>
            <input type="email" name="email" placeholder="Email" required class="regular-text"><br><br>
            <input type="text" name="dni" placeholder="DNI" required>
            <input type="text" name="iban" placeholder="IBAN" required class="regular-text"><br><br>
            <select name="sector">
                <option value="Varios">Oficios Varios</option>
                <option value="Enseñanza">Enseñanza e Intervención Social</option>
                <option value="Metal">Metal, Minería y Química</option>
                <option value="Hostelería">Hostelería y Turismo</option>
            </select>
            <input type="text" name="empresa" placeholder="Empresa"><br><br>
            <input type="submit" name="m_alta" class="button button-primary" value="Registrar Afiliado">
        </form>
    </div>
    <?php
}

/**
 * 3. INTERFAZ APP PWA (URL /montseny)
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
            <title>Montseny App</title>
            <style>
                body { font-family: sans-serif; background: #000; color: #fff; margin: 0; padding-bottom: 80px; }
                .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; position: sticky; top: 0; }
                .container { padding: 15px; }
                .card { background: #1a1a1a; border-left: 4px solid #CC0000; padding: 15px; margin-bottom: 15px; border-radius: 4px; }
                .carnet { background: linear-gradient(135deg, #333 0%, #000 100%); border: 2px solid #CC0000; padding: 20px; border-radius: 15px; position: relative; overflow: hidden; }
                .carnet h2 { margin: 0; color: #CC0000; font-size: 1.2rem; }
                .carnet-logo { position: absolute; top: 10px; right: 10px; opacity: 0.3; font-size: 3rem; font-weight: bold; }
                .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="bar">MONTSENY - <?php echo esc_html($nombre_local); ?></div>
            <div class="container">
                
                <?php if ( is_user_logged_in() ) : 
                    global $wpdb;
                    $user_id = get_current_user_id();
                    $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}montseny_afiliados WHERE user_id = %d", $user_id));
                ?>
                    <h3>Mi Carnet Digital</h3>
                    <div class="carnet">
                        <div class="carnet-logo">CNT</div>
                        <small style="color: #aaa;">AFILIADE CONFEDERADE</small>
                        <h2><?php echo wp_get_current_user()->display_name; ?></h2>
                        <p style="margin: 10px 0 0 0; font-size: 0.9rem;">
                            <strong>DNI:</strong> <?php echo Montseny_Crypto::decrypt($data->dni_cifrado); ?><br>
                            <strong>SECTOR:</strong> <?php echo esc_html($data->sector); ?><br>
                            <strong>SINDICATO:</strong> <?php echo esc_html($nombre_local); ?>
                        </p>
                    </div>
                    
                    <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" style="color: #666; display: block; text-align: center; margin-top: 30px;">Cerrar sesión</a>
                
                <?php else : ?>
                    <div class="card">
                        <h3>Bienvenide, compa</h3>
                        <p>Identifícate para acceder a tu carnet y noticias.</p>
                        <a href="<?php echo wp_login_url(site_url('/montseny')); ?>" class="btn">ENTRAR</a>
                    </div>
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
    ?>
    <div class="wrap">
        <h1>Panel Montseny</h1>
        <form method="post">
            <input type="text" name="l_name" value="<?php echo esc_attr($val); ?>">
            <input type="submit" name="m_save" class="button button-primary" value="Actualizar">
        </form>
    </div>
    <?php
}
