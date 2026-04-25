<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical y PWA. v1.3 - Formulario de Alta y Cifrado.
Version: 1.3
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 0. SISTEMA DE ACTUALIZACIÓN (Mantén tu usuario aquí)
 */
add_filter( 'pre_set_site_transient_update_plugins', 'montseny_check_update' );
function montseny_check_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;
    $user = 'CNTFormacionCentro'; 
    $repo = 'montseny';
    $plugin_slug = 'montseny/montseny.php';
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
 * 1. SEGURIDAD: CIFRADO DE DATOS
 */
class Montseny_Crypto {
    private static $method = 'aes-256-cbc';
    public static function encrypt($data) {
        $key = get_option('montseny_secret_key');
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$method));
        $encrypted = openssl_encrypt($data, self::$method, $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    public static function decrypt($data) {
        $key = get_option('montseny_secret_key');
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, self::$method, $key, 0, $iv);
    }
}

/**
 * 2. TABLAS Y ROLES (Añadimos DNI)
 */
register_activation_hook( __FILE__, 'montseny_instalar' );
function montseny_instalar() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $sql = "CREATE TABLE $tabla (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        dni_cifrado text,
        iban_cifrado text,
        genero varchar(20),
        fecha_nacimiento date,
        telefono varchar(20),
        direccion_postal text,
        trabajando tinyint(1) DEFAULT 1,
        empresa varchar(150),
        sector varchar(100),
        PRIMARY KEY  (id)
    ) {$wpdb->get_charset_collate()};";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    if (!get_option('montseny_secret_key')) update_option('montseny_secret_key', wp_generate_password(64));
}

/**
 * 3. PANEL DE ADMINISTRACIÓN: ALTA DE AFILIADOS
 */
add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_dash', 'dashicons-shield-alt', 2 );
    add_submenu_page( 'montseny-dashboard', 'Añadir Afiliado', 'Añadir Afiliado', 'manage_options', 'montseny-alta', 'montseny_alta_afiliado' );
});

function montseny_alta_afiliado() {
    if ( isset($_POST['m_alta']) ) {
        // 1. Crear usuario de WordPress
        $email = sanitize_email($_POST['email']);
        $nombre = sanitize_text_field($_POST['nombre']);
        $pass = wp_generate_password(12);
        
        $user_id = wp_create_user($email, $pass, $email);
        
        if ( is_wp_error($user_id) ) {
            echo '<div class="error"><p>Error: ' . $user_id->get_error_message() . '</p></div>';
        } else {
            // Actualizar nombre de WP
            wp_update_user(array('ID' => $user_id, 'display_name' => $nombre, 'role' => 'afiliade'));
            
            // 2. Guardar datos extras en nuestra tabla
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
            echo '<div class="updated"><p>¡Afiliado dado de alta! Contraseña enviada (o lista para la app).</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Nuevo Afiliado</h1>
        <form method="post" style="background:#white; padding:20px; border:1px solid #ccc; max-width:600px;">
            <h3>Datos de Acceso</h3>
            <input type="text" name="nombre" placeholder="Nombre completo" required class="regular-text"><br><br>
            <input type="email" name="email" placeholder="Email (será su usuario)" required class="regular-text"><br>
            
            <h3>Datos Sindicales (Cifrados)</h3>
            <input type="text" name="dni" placeholder="DNI" required>
            <input type="text" name="iban" placeholder="IBAN" required class="regular-text"><br>

            <h3>Perfil Personales</h3>
            <select name="genero">
                <option value="H">Hombre</option>
                <option value="M">Mujer</option>
                <option value="NB">No Binario</option>
                <option value="O">Otro</option>
            </select>
            <input type="date" name="f_nac" placeholder="Fecha Nacimiento">
            <input type="text" name="tel" placeholder="Teléfono"><br><br>
            <textarea name="dir" placeholder="Dirección postal" rows="2" style="width:100%"></textarea>

            <h3>Situación Laboral</h3>
            <label><input type="checkbox" name="trabaja" checked> ¿Trabaja actualmente?</label><br><br>
            <input type="text" name="empresa" placeholder="Empresa" class="regular-text"><br><br>
            <select name="sector">
                <option value="Servicios Varios">Servicios Varios</option>
                <option value="Metal">Metal, Minería y Química</option>
                <option value="Enseñanza">Enseñanza e Intervención Social</option>
                <option value="Construcción">Construcción y Madera</option>
                <option value="Hostelería">Hostelería y Turismo</option>
                <option value="Transportes">Transportes y Comunicaciones</option>
            </select><br><br>
            
            <input type="submit" name="m_alta" class="button button-primary" value="Registrar Afiliado">
        </form>
    </div>
    <?php
}

// [MANTÉN AQUÍ EL RESTO DE FUNCIONES: montseny_dash, montseny_mostrar_app, etc.]
// Nota: En montseny_mostrar_app() ahora ya puedes usar el nombre del usuario si está logueado:
// $current_user = wp_get_current_user();
// echo "Salud, " . $current_user->display_name;
