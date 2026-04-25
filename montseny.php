<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical v2.2 - Panel de Gestión Frontend completo para Tesorería.
Version: 2.2
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
    $current_version = isset($transient->checked[$plugin_slug]) ? $transient->checked[$plugin_slug] : '2.1';
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
 * 1. SEGURIDAD Y TABLAS
 */
register_activation_hook( __FILE__, 'montseny_setup' );
function montseny_setup() {
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
        PRIMARY KEY  (id)
    ) {$wpdb->get_charset_collate()};";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

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
 * 2. ADMINISTRACIÓN DE EQUIPO (WP-ADMIN)
 */
add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_dash_admin', 'dashicons-shield-alt', 2 );
    add_submenu_page( 'montseny-dashboard', 'Equipo', 'Equipo', 'manage_options', 'montseny-equipo', 'montseny_equipo_admin' );
});

function montseny_equipo_admin() {
    if ( isset($_POST['add_equipo']) ) {
        $user_id = wp_create_user(sanitize_email($_POST['e_mail']), $_POST['e_pass'], sanitize_email($_POST['e_mail']));
        if (!is_wp_error($user_id)) {
            wp_update_user(array('ID'=>$user_id, 'display_name'=>sanitize_text_field($_POST['e_nombre']), 'role'=>$_POST['e_rol']));
            echo '<div class="updated"><p>Compa añadido al equipo.</p></div>';
        }
    }
    ?>
    <div class="wrap"><h1>Gestión de Equipo Montseny</h1>
    <form method="post" style="max-width:400px; background:#fff; padding:20px; border:1px solid #ccc;">
        <input type="text" name="e_nombre" placeholder="Nombre" required class="regular-text"><br><br>
        <input type="email" name="e_mail" placeholder="Email" required class="regular-text"><br><br>
        <input type="text" name="e_pass" placeholder="Contraseña" required class="regular-text"><br><br>
        <select name="e_rol" style="width:100%"><option value="montseny_tesorero">Tesorería</option><option value="montseny_comunica">Comunicación</option></select><br><br>
        <input type="submit" name="add_equipo" class="button button-primary" value="Crear Acceso">
    </form></div>
    <?php
}

/**
 * 3. LÓGICA DE NOTICIAS
 */
function montseny_get_noticias() {
    $noticias = array();
    $posts = get_posts(array('numberposts' => 2));
    foreach($posts as $p) $noticias[] = array('f'=>'Web Local', 't'=>$p->post_title, 'l'=>get_permalink($p->ID));
    $tg = get_option('montseny_telegram_alias', 'cnt_nacional');
    $res = wp_remote_get("https://t.me/s/".$tg);
    if (!is_wp_error($res)) {
        $body = wp_remote_retrieve_body($res);
        if (preg_match_all('/<div class="tgme_widget_message_text[^>]*>(.*?)<\/div>/s', $body, $m)) {
            $noticias[] = array('f'=>'Telegram', 't'=>wp_trim_words(strip_tags(end($m[1])), 15), 'l'=>"https://t.me/s/".$tg);
        }
    }
    return $noticias;
}

/**
 * 4. ROUTER FRONTEND (APP + GESTIÓN)
 */
add_action( 'init', function() {
    $request = $_SERVER['REQUEST_URI'];
    
    // RUTA 1: APP AFILIADO (/montseny)
    if ( strpos($request, '/montseny') !== false && strpos($request, '/montseny/gestion') === false ) {
        if (isset($_POST['m_login'])) {
            $u = wp_signon(array('user_login'=>$_POST['log'],'user_password'=>$_POST['pwd'],'remember'=>true), false);
            wp_redirect(site_url(is_wp_error($u) ? '/montseny/?err=1' : '/montseny/')); exit;
        }
        montseny_ui_app(); exit;
    }

    // RUTA 2: PANEL GESTIÓN (/montseny/gestion)
    if ( strpos($request, '/montseny/gestion') !== false ) {
        if (!current_user_can('montseny_tesorero') && !current_user_can('manage_options')) {
            wp_redirect(site_url('/montseny/')); exit;
        }
        montseny_ui_gestion(); exit;
    }
});

/**
 * 5. INTERFAZ APP AFILIADO
 */
function montseny_ui_app() {
    $nombre_local = get_option('montseny_nombre_local', 'CNT');
    $noticias = montseny_get_noticias();
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #000; color: #fff; margin: 0; padding-bottom: 60px; }
        .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; position: sticky; top: 0; }
        .container { padding: 15px; }
        .card { background: #1a1a1a; border-left: 4px solid #CC0000; padding: 15px; margin-bottom: 12px; border-radius: 4px; }
        .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 10px; border:none; width:100%; cursor:pointer; }
        .btn-black { background: #333; margin-top: 30px; }
    </style></head><body>
    <div class="bar">MONTSENY - <?php echo $nombre_local; ?></div>
    <div class="container">
        <?php if ( is_user_logged_in() ) : 
            $u = wp_get_current_user();
            global $wpdb;
            $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}montseny_afiliados WHERE user_id = %d", $u->ID));
        ?>
            <div class="card">
                <small style="color:#CC0000">SALUD, COMPA</small>
                <h2><?php echo $u->display_name; ?></h2>
                <p>Ramo: <?php echo $data ? esc_html($data->ramo) : 'No asignado'; ?></p>
            </div>

            <h3>Noticias</h3>
            <?php foreach($noticias as $n): ?>
                <div class="card">
                    <small style="color:#CC0000"><?php echo $n['f']; ?></small>
                    <a href="<?php echo $n['l']; ?>" target="_blank" style="color:#fff; text-decoration:none;"><h4><?php echo $n['t']; ?></h4></a>
                </div>
            <?php endforeach; ?>

            <?php if ( current_user_can('montseny_tesorero') || current_user_can('manage_options') ) : ?>
                <a href="<?php echo site_url('/montseny/gestion'); ?>" class="btn btn-black">⚙️ PANEL DE GESTIÓN</a>
            <?php endif; ?>

            <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" style="color:#444; display:block; text-align:center; margin-top:30px; text-decoration:none;">Cerrar Sesión</a>
        <?php else : ?>
            <form method="post">
                <h3>Identificación</h3>
                <input type="text" name="log" placeholder="Email" style="width:100%; padding:12px; margin-bottom:10px; border-radius:8px; border:none;">
                <input type="password" name="pwd" placeholder="Contraseña" style="width:100%; padding:12px; margin-bottom:10px; border-radius:8px; border:none;">
                <input type="hidden" name="m_login" value="1">
                <button type="submit" class="btn">ENTRAR</button>
            </form>
        <?php endif; ?>
    </div></body></html>
    <?php
}

/**
 * 6. INTERFAZ GESTIÓN (TESORERO)
 */
function montseny_ui_gestion() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    
    // Lógica para guardar edición
    if (isset($_POST['save_afiliado'])) {
        $uid = intval($_POST['user_id']);
        wp_update_user(array('ID'=>$uid, 'user_email'=>sanitize_email($_POST['email']), 'display_name'=>sanitize_text_field($_POST['nombre'])));
        if (!empty($_POST['pass'])) wp_set_password($_POST['pass'], $uid);
        
        $wpdb->update($tabla, array(
            'ramo' => sanitize_text_field($_POST['ramo']),
            'etiquetas' => sanitize_text_field($_POST['tags']),
            'observaciones' => sanitize_textarea_field($_POST['obs']),
            'direccion_cifrada' => Montseny_Crypto::encrypt($_POST['dir']),
            'iban_cifrado' => Montseny_Crypto::encrypt($_POST['iban'])
        ), array('user_id'=>$uid));
        wp_redirect(site_url('/montseny/gestion/?msg=ok')); exit;
    }

    $afiliados = $wpdb->get_results("SELECT * FROM $tabla");
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #111; color: #fff; margin: 0; }
        .bar { background: #333; padding: 15px; text-align: center; font-weight: bold; border-bottom: 2px solid #CC0000; }
        .container { padding: 15px; }
        .list-item { background: #222; padding: 15px; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #444; }
        .btn-edit { background: #CC0000; color: #fff; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 0.8rem; }
        .edit-form { background: #222; padding: 20px; border-radius: 10px; }
        input, select, textarea { width: 100%; padding: 10px; margin-bottom: 15px; background: #333; border: 1px solid #555; color: #fff; border-radius: 5px; box-sizing: border-box; }
        label { color: #CC0000; font-weight: bold; font-size: 0.8rem; display: block; margin-bottom: 5px; }
    </style></head><body>
    <div class="bar">GESTIÓN SINDICAL - MONTSENY</div>
    <div class="container">
        <a href="<?php echo site_url('/montseny'); ?>" style="color:#aaa; text-decoration:none; font-size:0.8rem;">← Volver a la App</a>
        
        <?php if (isset($_GET['edit'])) : 
            $edit_id = intval($_GET['edit']);
            $comp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $edit_id));
            $u_info = get_userdata($edit_id);
        ?>
            <h3>Editar Compa: <?php echo $u_info->display_name; ?></h3>
            <form method="post" class="edit-form">
                <input type="hidden" name="user_id" value="<?php echo $edit_id; ?>">
                <label>Nombre Completo</label><input type="text" name="nombre" value="<?php echo $u_info->display_name; ?>">
                <label>Email (Login)</label><input type="email" name="email" value="<?php echo $u_info->user_email; ?>">
                <label>Nueva Contraseña (dejar en blanco para no cambiar)</label><input type="text" name="pass" placeholder="Sólo si quieres cambiarla">
                <label>Ramo</label><input type="text" name="ramo" value="<?php echo $comp->ramo; ?>">
                <label>Etiquetas (separadas por comas)</label><input type="text" name="tags" value="<?php echo $comp->etiquetas; ?>">
                <label>Dirección Postal (Cifrada)</label><input type="text" name="dir" value="<?php echo Montseny_Crypto::decrypt($comp->direccion_cifrada); ?>">
                <label>IBAN (Cifrado)</label><input type="text" name="iban" value="<?php echo Montseny_Crypto::decrypt($comp->iban_cifrado); ?>">
                <label>Observaciones Abiertas</label><textarea name="obs"><?php echo $comp->observaciones; ?></textarea>
                <button type="submit" name="save_afiliado" class="btn-edit" style="width:100%; padding:15px; font-size:1rem;">GUARDAR CAMBIOS</button>
            </form>
        <?php else : ?>
            <h3>Lista de Afiliación</h3>
            <?php foreach($afiliados as $af): $u = get_userdata($af->user_id); ?>
                <div class="list-item">
                    <div><strong><?php echo $u->display_name; ?></strong><br><small style="color:#888;"><?php echo $af->ramo; ?></small></div>
                    <a href="?edit=<?php echo $af->user_id; ?>" class="btn-edit">EDITAR</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div></body></html>
    <?php
}

function montseny_dash_admin() {
    if (isset($_POST['m_save_conf'])) {
        update_option('montseny_nombre_local', sanitize_text_field($_POST['l_name']));
        update_option('montseny_telegram_alias', sanitize_text_field($_POST['t_alias']));
        echo '<div class="updated"><p>Configuración guardada.</p></div>';
    }
    $l = get_option('montseny_nombre_local', 'CNT Ciudad Real');
    $t = get_option('montseny_telegram_alias', 'cnt_nacional');
    ?>
    <div class="wrap"><h1>Configuración Montseny</h1>
        <form method="post"><table class="form-table">
            <tr><th>Nombre del Sindicato</th><td><input type="text" name="l_name" value="<?php echo esc_attr($l); ?>"></td></tr>
            <tr><th>Alias de Telegram (Público)</th><td><input type="text" name="t_alias" value="<?php echo esc_attr($t); ?>"></td></tr>
        </table><input type="submit" name="m_save_conf" class="button button-primary" value="Guardar"></form>
    </div>
    <?php
}
