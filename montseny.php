<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical v2.1 - Zona de Gestión Frontend, Equipo y Noticias Telegram.
Version: 2.1
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
    $current_version = isset($transient->checked[$plugin_slug]) ? $transient->checked[$plugin_slug] : '2.0';
    if ( isset($release->tag_name) && version_compare( $release->tag_name, $current_version, '>' ) ) {
        $obj = new stdClass(); $obj->slug = $plugin_slug; $obj->new_version = $release->tag_name;
        $obj->url = "https://github.com/$user/$repo"; $obj->package = $release->zipball_url;
        $transient->response[$plugin_slug] = $obj;
    }
    return $transient;
}

/**
 * 1. ROLES Y SEGURIDAD
 */
register_activation_hook( __FILE__, 'montseny_setup' );
function montseny_setup() {
    // Tablas
    global $wpdb;
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $sql = "CREATE TABLE $tabla (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        genero varchar(20),
        ramo varchar(100),
        telefono varchar(20),
        direccion_cifrada text,
        iban_cifrado text,
        etiquetas text,
        observaciones text,
        fecha_alta date,
        PRIMARY KEY  (id)
    ) {$wpdb->get_charset_collate()};";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Roles
    add_role( 'montseny_tesorero', 'Tesorería Montseny', array( 'read' => true ) );
    add_role( 'montseny_comunica', 'Comunicación Montseny', array( 'read' => true ) );
    add_role( 'afiliade', 'Afiliade Montseny', array( 'read' => true ) );

    if (!get_option('montseny_secret_key')) update_option('montseny_secret_key', wp_generate_password(64));
}

class Montseny_Crypto {
    public static function encrypt($data) {
        if(empty($data)) return '';
        $key = get_option('montseny_secret_key');
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    public static function decrypt($data) {
        if(empty($data)) return '';
        $key = get_option('montseny_secret_key');
        $parts = explode('::', base64_decode($data), 2);
        return (count($parts) < 2) ? '---' : openssl_decrypt($parts[0], 'aes-256-cbc', $key, 0, $parts[1]);
    }
}

/**
 * 2. GESTIÓN DE EQUIPO (Para el Admin en WP-Admin)
 */
add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_dash_admin', 'dashicons-shield-alt', 2 );
    add_submenu_page( 'montseny-dashboard', 'Gestionar Equipo', 'Gestionar Equipo', 'manage_options', 'montseny-equipo', 'montseny_equipo_admin' );
});

function montseny_equipo_admin() {
    if ( isset($_POST['crear_equipo']) ) {
        $email = sanitize_email($_POST['e_mail']);
        $user_id = wp_create_user($email, $_POST['e_pass'], $email);
        if (!is_wp_error($user_id)) {
            wp_update_user(array('ID' => $user_id, 'display_name' => sanitize_text_field($_POST['e_nombre'])));
            $u = new WP_User($user_id);
            $u->set_role($_POST['e_rol']);
            echo '<div class="updated"><p>Acceso creado para '.$_POST['e_nombre'].'</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Gestionar Equipo Montseny</h1>
        <p>Crea accesos para Tesorería o Comunicación aquí.</p>
        <form method="post" style="background:#fff; padding:20px; border:1px solid #ccc; max-width:400px;">
            <input type="text" name="e_nombre" placeholder="Nombre" required class="regular-text"><br><br>
            <input type="email" name="e_mail" placeholder="Email" required class="regular-text"><br><br>
            <input type="text" name="e_pass" placeholder="Contraseña" required class="regular-text"><br><br>
            <select name="e_rol" style="width:100%">
                <option value="montseny_tesorero">Tesorería</option>
                <option value="montseny_comunica">Comunicación</option>
            </select><br><br>
            <input type="submit" name="crear_equipo" class="button button-primary" value="Añadir al Equipo">
        </form>
    </div>
    <?php
}

/**
 * 3. LOGICA DE NOTICIAS (WEB + TELEGRAM)
 */
function montseny_get_noticias() {
    $noticias = array();
    // 1. Web Local
    $posts = get_posts(array('numberposts' => 2));
    foreach($posts as $p) $noticias[] = array('f'=>'Local', 't'=>$p->post_title, 'l'=>get_permalink($p->ID));
    
    // 2. Telegram
    $tg_alias = get_option('montseny_telegram_alias', 'cnt_nacional');
    $tg_res = wp_remote_get("https://t.me/s/".$tg_alias);
    if (!is_wp_error($tg_res)) {
        $body = wp_remote_retrieve_body($tg_res);
        if (preg_match_all('/<div class="tgme_widget_message_text[^>]*>(.*?)<\/div>/s', $body, $m)) {
            $noticias[] = array('f'=>'Telegram', 't'=>wp_trim_words(strip_tags(end($m[1])), 15), 'l'=>"https://t.me/s/".$tg_alias);
        }
    }
    return $noticias;
}

/**
 * 4. INTERFAZ APP (Frontend)
 */
add_action( 'init', function() {
    if ( strpos( $_SERVER['REQUEST_URI'], '/montseny' ) !== false ) {
        // Manejo de login interno
        if (isset($_POST['m_login'])) {
            $user = wp_signon(array('user_login'=>$_POST['log'],'user_password'=>$_POST['pwd'],'remember'=>true), false);
            wp_redirect(site_url(is_wp_error($user) ? '/montseny/?error=1' : '/montseny/')); exit;
        }

        $nombre_local = get_option('montseny_nombre_local', 'CNT');
        $noticias = montseny_get_noticias();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: sans-serif; background: #000; color: #fff; margin: 0; padding-bottom: 50px; }
                .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; position: sticky; top: 0; z-index: 100; }
                .container { padding: 15px; }
                .card { background: #1a1a1a; border-left: 4px solid #CC0000; padding: 12px; margin-bottom: 12px; }
                .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; border: none; width: 100%; margin-top: 10px; cursor: pointer;}
                .admin-btn { background: #333; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class="bar">MONTSENY - <?php echo esc_html($nombre_local); ?></div>
            <div class="container">
                <?php if ( is_user_logged_in() ) : 
                    $u = wp_get_current_user();
                ?>
                    <div class="card">
                        <small style="color:#CC0000">SALUD, COMPA</small>
                        <h2><?php echo $u->display_name; ?></h2>
                        <p>Sindicato: <?php echo $nombre_local; ?></p>
                    </div>

                    <h3>Actualidad</h3>
                    <?php foreach($noticias as $n): ?>
                        <div class="card">
                            <small style="color:#CC0000"><?php echo $n['f']; ?></small>
                            <a href="<?php echo $n['l']; ?>" target="_blank" style="color:#fff; text-decoration:none;"><h4><?php echo $n['t']; ?></h4></a>
                        </div>
                    <?php endforeach; ?>

                    <?php if ( current_user_can('montseny_tesorero') || current_user_can('manage_options') ) : ?>
                        <a href="<?php echo site_url('/montseny/gestion'); ?>" class="btn admin-btn">⚙️ PANEL DE GESTIÓN</a>
                    <?php endif; ?>

                    <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" style="color:#444; display:block; text-align:center; margin-top:30px; text-decoration:none;">Cerrar Sesión</a>
                <?php else : ?>
                    <form method="post">
                        <h3>Acceso</h3>
                        <input type="text" name="log" placeholder="Email" style="width:100%; padding:12px; margin-bottom:10px; box-sizing:border-box;">
                        <input type="password" name="pwd" placeholder="Contraseña" style="width:100%; padding:12px; margin-bottom:10px; box-sizing:border-box;">
                        <input type="hidden" name="m_login" value="1">
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

function montseny_dash_admin() {
    if (isset($_POST['m_save_conf'])) {
        update_option('montseny_nombre_local', sanitize_text_field($_POST['l_name']));
        update_option('montseny_telegram_alias', sanitize_text_field($_POST['t_alias']));
        echo '<div class="updated"><p>Configuración guardada.</p></div>';
    }
    $l = get_option('montseny_nombre_local', 'CNT Ciudad Real');
    $t = get_option('montseny_telegram_alias', 'cnt_nacional');
    ?>
    <div class="wrap">
        <h1>Configuración Montseny</h1>
        <form method="post">
            <table class="form-table">
                <tr><th>Nombre del Sindicato</th><td><input type="text" name="l_name" value="<?php echo esc_attr($l); ?>"></td></tr>
                <tr><th>Alias de Telegram (sin @)</th><td><input type="text" name="t_alias" value="<?php echo esc_attr($t); ?>"></td></tr>
            </table>
            <input type="submit" name="m_save_conf" class="button button-primary" value="Guardar">
        </form>
    </div>
    <?php
}
