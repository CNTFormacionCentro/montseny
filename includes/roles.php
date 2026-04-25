<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('init', function() {
    if ( ! get_role( 'montseny_tesorero' ) ) add_role( 'montseny_tesorero', 'Tesorería Montseny', array( 'read' => true ) );
    if ( ! get_role( 'montseny_comunica' ) ) add_role( 'montseny_comunica', 'Comunicación Montseny', array( 'read' => true ) );
    if ( ! get_role( 'afiliade' ) ) add_role( 'afiliade', 'Afiliade Montseny', array( 'read' => true ) );
});

add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_settings_page', 'dashicons-shield-alt', 2 );
    add_submenu_page( 'montseny-dashboard', 'Equipo', 'Equipo', 'manage_options', 'montseny-equipo', 'montseny_equipo_admin' );
});

function montseny_settings_page() {
    if (isset($_POST['m_save_settings'])) {
        update_option('montseny_nombre_local', sanitize_text_field($_POST['l_name']));
        update_option('montseny_url_local', esc_url_raw($_POST['u_local']));
        update_option('montseny_url_confederal', esc_url_raw($_POST['u_conf']));
        update_option('montseny_telegram_alias', sanitize_text_field($_POST['t_alias']));
        echo '<div class="updated"><p>Configuración guardada.</p></div>';
    }
    $l = get_option('montseny_nombre_local', 'CNT Ciudad Real');
    $u_l = get_option('montseny_url_local', 'https://ciudadreal.cnt.es');
    $u_c = get_option('montseny_url_confederal', 'https://www.cnt.es');
    $t = get_option('montseny_telegram_alias', 'cnt_nacional');
    ?>
    <div class="wrap">
        <h1>Configuración Montseny</h1>
        <form method="post" style="background:#fff; padding:20px; border:1px solid #ccc; max-width:600px;">
            <p><label>Nombre Sindicato:</label><br><input type="text" name="l_name" value="<?php echo $l; ?>" class="regular-text"></p>
            <p><label>URL Web Local (con https://):</label><br><input type="url" name="u_local" value="<?php echo $u_l; ?>" class="regular-text"></p>
            <p><label>URL Web Confederal:</label><br><input type="url" name="u_conf" value="<?php echo $u_c; ?>" class="regular-text"></p>
            <p><label>Alias Telegram (sin @):</label><br><input type="text" name="t_alias" value="<?php echo $t; ?>" class="regular-text"></p>
            <input type="submit" name="m_save_settings" class="button button-primary" value="Guardar">
        </form>
    </div>
    <?php
}

function montseny_equipo_admin() {
    if (isset($_GET['action']) && $_GET['action'] == 'delete') wp_delete_user(intval($_GET['user_id']));
    if (isset($_POST['add_eq'])) {
        $uid = wp_create_user(sanitize_email($_POST['em']), $_POST['ps'], sanitize_email($_POST['em']));
        if (!is_wp_error($uid)) {
            wp_update_user(array('ID'=>$uid, 'display_name'=>sanitize_text_field($_POST['no']), 'role'=>$_POST['ro']));
        }
    }
    $equipo = get_users(array('role__in' => array('montseny_tesorero', 'montseny_comunica')));
    ?>
    <div class="wrap">
        <h1>Equipo de Gestión</h1>
        <form method="post" style="margin-bottom:20px;">
            <input type="text" name="no" placeholder="Nombre" required>
            <input type="email" name="em" placeholder="Email" required>
            <input type="text" name="ps" placeholder="Pass" required>
            <select name="ro"><option value="montseny_tesorero">Tesorería</option><option value="montseny_comunica">Comunicación</option></select>
            <input type="submit" name="add_eq" value="Añadir">
        </form>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Nombre</th><th>Rol</th><th>Acción</th></tr></thead>
            <tbody>
                <?php foreach($equipo as $u): ?>
                <tr><td><?php echo $u->display_name; ?></td><td><?php echo str_replace('montseny_', '', $u->roles[0]); ?></td><td><a href="?page=montseny-equipo&action=delete&user_id=<?php echo $u->ID; ?>">Quitar</a></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
