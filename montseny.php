<?php
/*
Plugin Name: Montseny
Plugin URI: https://ciudadreal.cnt.es
Description: Gestión sindical v2.3 - Versión Integral (Gestión Frontend, Importador A-K, Cifrado y Estabilidad).
Version: 2.3
Author: Montseny Project
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 0. SISTEMA DE ACTUALIZACIÓN Y ESTABILIDAD DE CARPETAS
 */
add_filter( 'pre_set_site_transient_update_plugins', 'montseny_check_update' );
function montseny_check_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;
    $user = 'CNTFormacionCentro'; $repo = 'montseny';
    $plugin_slug = plugin_basename(__FILE__); 
    $url = "https://api.github.com/repos/$user/$repo/releases/latest";
    $response = wp_remote_get( $url, array( 'user-agent' => 'WordPress' ) );
    if ( is_wp_error( $response ) ) return $transient;
    $release = json_decode( wp_remote_retrieve_body( $response ) );
    $current_version = $transient->checked[$plugin_slug];
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
        fecha_nacimiento varchar(50),
        telefono varchar(20),
        direccion_cifrada text,
        iban_cifrado text,
        ramo varchar(100),
        etiquetas text,
        observaciones text,
        fecha_alta date,
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
    private static $m = 'aes-256-cbc';
    public static function encrypt($d) {
        if(empty($d)) return '';
        $k = get_option('montseny_secret_key'); $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$m));
        return base64_encode(openssl_encrypt($d, self::$m, $k, 0, $iv) . '::' . $iv);
    }
    public static function decrypt($d) {
        if(empty($d)) return '';
        $k = get_option('montseny_secret_key'); $p = explode('::', base64_decode($d), 2);
        return (count($p)<2) ? '---' : openssl_decrypt($p[0], self::$m, $k, 0, $p[1]);
    }
}

/**
 * 2. GESTIÓN DE EQUIPO (ADMIN BACKEND)
 */
add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_dash_admin', 'dashicons-shield-alt', 2 );
    add_submenu_page( 'montseny-dashboard', 'Equipo', 'Equipo', 'manage_options', 'montseny-equipo', 'montseny_equipo_admin' );
});

function montseny_equipo_admin() {
    if (isset($_POST['add_eq'])) {
        $uid = wp_create_user(sanitize_email($_POST['em']), $_POST['ps'], sanitize_email($_POST['em']));
        if (!is_wp_error($uid)) {
            wp_update_user(array('ID'=>$uid, 'display_name'=>sanitize_text_field($_POST['no']), 'role'=>$_POST['ro']));
            echo '<div class="updated"><p>Acceso creado.</p></div>';
        }
    }
    ?>
    <div class="wrap"><h1>Crear Equipo de Gestión</h1>
    <form method="post" style="max-width:400px; background:#fff; padding:20px; border:1px solid #ccc;">
        <input type="text" name="no" placeholder="Nombre" required class="regular-text"><br><br>
        <input type="email" name="em" placeholder="Email" required class="regular-text"><br><br>
        <input type="text" name="ps" placeholder="Contraseña" required class="regular-text"><br><br>
        <select name="ro" style="width:100%"><option value="montseny_tesorero">Tesorería</option><option value="montseny_comunica">Comunicación</option></select><br><br>
        <input type="submit" name="add_eq" class="button button-primary" value="Crear Acceso">
    </form></div>
    <?php
}

/**
 * 3. INTERFAZ FRONTEND (APP + GESTIÓN)
 */
add_action( 'init', function() {
    $req = $_SERVER['REQUEST_URI'];
    if ( strpos($req, '/montseny') !== false && strpos($req, '/montseny/gestion') === false ) {
        if (isset($_POST['m_login'])) {
            $u = wp_signon(array('user_login'=>$_POST['log'],'user_password'=>$_POST['pwd'],'remember'=>true), false);
            wp_redirect(site_url(is_wp_error($u) ? '/montseny/?err=1' : '/montseny/')); exit;
        }
        montseny_ui_app(); exit;
    }
    if ( strpos($req, '/montseny/gestion') !== false ) {
        if (!current_user_can('montseny_tesorero') && !current_user_can('manage_options')) { wp_redirect(site_url('/montseny/')); exit; }
        montseny_ui_gestion(); exit;
    }
});

function montseny_ui_app() {
    $local = get_option('montseny_nombre_local', 'CNT');
    $tg = get_option('montseny_telegram_alias', 'cnt_nacional');
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #000; color: #fff; margin: 0; padding-bottom: 60px; }
        .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; }
        .container { padding: 15px; }
        .card { background: #1a1a1a; border-left: 4px solid #CC0000; padding: 15px; margin-bottom: 12px; }
        .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; border:none; width:100%; cursor:pointer; }
    </style></head><body>
    <div class="bar">MONTSENY - <?php echo $local; ?></div>
    <div class="container">
        <?php if ( is_user_logged_in() ) : $u = wp_get_current_user(); ?>
            <div class="card"><h2>Salud, <?php echo $u->display_name; ?></h2><p>Acceso Afiliade</p></div>
            <?php if (current_user_can('montseny_tesorero') || current_user_can('manage_options')) : ?>
                <a href="<?php echo site_url('/montseny/gestion'); ?>" class="btn" style="background:#333;">⚙️ PANEL DE GESTIÓN</a>
            <?php endif; ?>
            <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" style="color:#444; display:block; text-align:center; margin-top:30px;">Cerrar Sesión</a>
        <?php else : ?>
            <form method="post">
                <h3>Identificación</h3>
                <input type="text" name="log" placeholder="Email" style="width:100%; padding:12px; margin-bottom:10px; border-radius:8px;">
                <input type="password" name="pwd" placeholder="Contraseña" style="width:100%; padding:12px; margin-bottom:10px; border-radius:8px;">
                <input type="hidden" name="m_login" value="1"><button type="submit" class="btn">ENTRAR</button>
            </form>
        <?php endif; ?>
    </div></body></html>
    <?php
}

function montseny_ui_gestion() {
    global $wpdb; $tabla = $wpdb->prefix . 'montseny_afiliados';
    
    // IMPORTADOR A-K
    if (isset($_POST['m_import']) && !empty($_FILES['csv']['tmp_name'])) {
        $f = fopen($_FILES['csv']['tmp_name'], 'r'); fgetcsv($f, 0, ";");
        while (($r = fgetcsv($f, 0, ";")) !== FALSE) {
            $nom = $r[0].' '.$r[1]; $tmp_u = 'tmp_'.time().rand(10,99);
            $uid = wp_create_user($tmp_u, wp_generate_password(12), $tmp_u.'@temporal.cnt');
            if(!is_wp_error($uid)) {
                wp_update_user(array('ID'=>$uid, 'display_name'=>$nom));
                $u = new WP_User($uid); $u->set_role('afiliade');
                $wpdb->insert($tabla, array('user_id'=>$uid, 'genero'=>$r[3], 'ramo'=>$r[5], 'fecha_alta'=>$r[8], 'observaciones'=>$r[10], 'etiquetas'=>$r[4]));
            }
        }
        fclose($f); wp_redirect(site_url('/montseny/gestion/?m=ok')); exit;
    }

    // GUARDAR EDICIÓN
    if (isset($_POST['save_comp'])) {
        $uid = intval($_POST['uid']);
        wp_update_user(array('ID'=>$uid, 'user_email'=>sanitize_email($_POST['em']), 'display_name'=>sanitize_text_field($_POST['no'])));
        if(!empty($_POST['pw'])) wp_set_password($_POST['pw'], $uid);
        $wpdb->update($tabla, array(
            'ramo'=>$_POST['ra'], 'etiquetas'=>$_POST['ta'], 'observaciones'=>$_POST['ob'],
            'direccion_cifrada'=>Montseny_Crypto::encrypt($_POST['di']), 'iban_cifrado'=>Montseny_Crypto::encrypt($_POST['ib'])
        ), array('user_id'=>$uid));
        wp_redirect(site_url('/montseny/gestion/')); exit;
    }

    $afiliados = $wpdb->get_results("SELECT * FROM $tabla");
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #111; color: #fff; margin: 0; }
        .bar { background: #333; padding: 15px; text-align: center; border-bottom: 2px solid #CC0000; font-weight:bold; }
        .container { padding: 15px; }
        .item { background: #222; padding: 15px; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .btn-r { background: #CC0000; color: #fff; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 0.8rem; border:none; }
        input, select, textarea { width: 100%; padding: 10px; margin-bottom: 10px; background: #333; color: #fff; border: 1px solid #555; box-sizing:border-box; }
        .aviso { background: #442200; padding: 10px; border-radius: 5px; font-size: 0.8rem; margin-bottom: 15px; border-left: 4px solid #ff9900; }
    </style></head><body>
    <div class="bar">GESTIÓN MONTSENY</div>
    <div class="container">
        <?php if (isset($_GET['edit'])) : 
            $uid = intval($_GET['edit']); $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $uid)); $u = get_userdata($uid);
        ?>
            <h3>Editar: <?php echo $u->display_name; ?></h3>
            <form method="post">
                <input type="hidden" name="uid" value="<?php echo $uid; ?>">
                <label>Nombre</label><input type="text" name="no" value="<?php echo $u->display_name; ?>">
                <label>Email (Login)</label><input type="email" name="em" value="<?php echo (strpos($u->user_email, '@temporal.cnt')===false)?$u->user_email:''; ?>" placeholder="Añadir email real">
                <label>Nueva Contraseña</label><input type="text" name="pw" placeholder="Solo si quieres cambiarla">
                <label>IBAN (Cifrado)</label><input type="text" name="ib" value="<?php echo Montseny_Crypto::decrypt($c->iban_cifrado); ?>">
                <label>Dirección (Cifrada)</label><input type="text" name="di" value="<?php echo Montseny_Crypto::decrypt($c->direccion_cifrada); ?>">
                <label>Etiquetas</label><input type="text" name="ta" value="<?php echo $c->etiquetas; ?>">
                <label>Observaciones</label><textarea name="ob"><?php echo $c->observaciones; ?></textarea>
                <button type="submit" name="save_comp" class="btn-r" style="width:100%">GUARDAR CAMBIOS</button>
            </form>
        <?php else : ?>
            <div class="aviso"><strong>Importante:</strong> El campo Edad del Excel se ignora por errores en la app origen.</div>
            <form method="post" enctype="multipart/form-data" style="margin-bottom:30px; padding:15px; border:1px dashed #555;">
                <label>Importar Excel A-K (CSV ;)</label><br><input type="file" name="csv" accept=".csv" required><br>
                <button type="submit" name="m_import" class="btn-r">SUBIR E IMPORTAR</button>
            </form>
            <h3>Afiliación</h3>
            <?php foreach($afiliados as $af): $u = get_userdata($af->user_id); ?>
                <div class="item">
                    <span><?php echo $u->display_name; ?> <?php echo (strpos($u->user_email, '@temporal.cnt')!==false)?'⚠️':''; ?></span>
                    <a href="?edit=<?php echo $af->user_id; ?>" class="btn-r">EDITAR</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div></body></html>
    <?php
}

function montseny_dash_admin() {
    if (isset($_POST['m_s'])) { update_option('montseny_nombre_local', sanitize_text_field($_POST['l'])); update_option('montseny_telegram_alias', sanitize_text_field($_POST['t'])); echo '<div class="updated"><p>Ok.</p></div>'; }
    $l = get_option('montseny_nombre_local', 'CNT'); $t = get_option('montseny_telegram_alias', 'cnt_nacional');
    ?>
    <div class="wrap"><h1>Configuración Montseny</h1><form method="post">
        Sindicato: <input type="text" name="l" value="<?php echo esc_attr($l); ?>"><br>
        Telegram Alias: <input type="text" name="t" value="<?php echo esc_attr($t); ?>"><br>
        <input type="submit" name="m_s" class="button button-primary" value="Guardar">
    </form></div>
    <?php
}
