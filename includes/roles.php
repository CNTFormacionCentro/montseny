<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CONFIGURACIÓN DE ROLES
 */
add_action('init', 'montseny_crear_roles_equipo');
function montseny_crear_roles_equipo() {
    if ( ! get_role( 'montseny_tesorero' ) ) {
        add_role( 'montseny_tesorero', 'Tesorería Montseny', array( 'read' => true ) );
    }
    if ( ! get_role( 'montseny_comunica' ) ) {
        add_role( 'montseny_comunica', 'Comunicación Montseny', array( 'read' => true ) );
    }
    if ( ! get_role( 'afiliade' ) ) {
        add_role( 'afiliade', 'Afiliade Montseny', array( 'read' => true ) );
    }
}

/**
 * MENÚS EN EL BACKEND
 */
add_action( 'admin_menu', function() {
    // Menú Principal: Configuración
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_settings_page', 'dashicons-shield-alt', 2 );
    // Submenú: Equipo
    add_submenu_page( 'montseny-dashboard', 'Equipo', 'Equipo', 'manage_options', 'montseny-equipo', 'montseny_equipo_admin' );
});

// PÁGINA DE AJUSTES (Sindicato y Telegram)
function montseny_settings_page() {
    if (isset($_POST['m_save_settings'])) {
        update_option('montseny_nombre_local', sanitize_text_field($_POST['l_name']));
        update_option('montseny_telegram_alias', sanitize_text_field($_POST['t_alias']));
        echo '<div class="updated"><p>Configuración guardada.</p></div>';
    }
    $l = get_option('montseny_nombre_local', 'CNT');
    $t = get_option('montseny_telegram_alias', 'cnt_nacional');
    ?>
    <div class="wrap">
        <h1>Configuración General Montseny</h1>
        <form method="post" style="background:#fff; padding:20px; border:1px solid #ccc; max-width:500px; margin-top:20px;">
            <p>
                <label><strong>Nombre del Sindicato (Local)</strong></label><br>
                <input type="text" name="l_name" value="<?php echo esc_attr($l); ?>" class="regular-text">
            </p>
            <p>
                <label><strong>Alias de Telegram (Público, sin @)</strong></label><br>
                <input type="text" name="t_alias" value="<?php echo esc_attr($t); ?>" class="regular-text">
            </p>
            <input type="submit" name="m_save_settings" class="button button-primary" value="Guardar Cambios">
        </form>
    </div>
    <?php
}

// PÁGINA DE EQUIPO
function montseny_equipo_admin() {
    if (isset($_GET['action']) && $_GET['action'] == 'delete') {
        wp_delete_user(intval($_GET['user_id']));
        echo '<div class="updated"><p>Acceso eliminado.</p></div>';
    }
    if (isset($_POST['add_eq'])) {
        $uid = wp_create_user(sanitize_email($_POST['em']), $_POST['ps'], $_POST['em']);
        if (!is_wp_error($uid)) {
            wp_update_user(array('ID'=>$uid, 'display_name'=>sanitize_text_field($_POST['no']), 'role'=>$_POST['ro']));
            echo '<div class="updated"><p>Compañere añadido.</p></div>';
        }
    }
    $equipo = get_users(array('role__in' => array('montseny_tesorero', 'montseny_comunica')));
    ?>
    <div class="wrap">
        <h1>Gestión de Equipo</h1>
        <div style="display:flex; gap:20px; margin-top:20px;">
            <form method="post" style="background:#fff; padding:20px; border:1px solid #ccc; width:300px;">
                <h3>Añadir al Equipo</h3>
                <input type="text" name="no" placeholder="Nombre" required style="width:100%; margin-bottom:10px;"><br>
                <input type="email" name="em" placeholder="Email" required style="width:100%; margin-bottom:10px;"><br>
                <input type="text" name="ps" placeholder="Contraseña App" required style="width:100%; margin-bottom:10px;"><br>
                <select name="ro" style="width:100%; margin-bottom:15px;">
                    <option value="montseny_tesorero">Tesorería</option>
                    <option value="montseny_comunica">Comunicación</option>
                </select>
                <input type="submit" name="add_eq" class="button button-primary" value="Crear Acceso">
            </form>
            <table class="wp-list-table widefat fixed striped" style="flex:1;">
                <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach($equipo as $u): ?>
                    <tr>
                        <td><strong><?php echo $u->display_name; ?></strong></td>
                        <td><?php echo $u->user_email; ?></td>
                        <td><?php echo (in_array('montseny_tesorero', $u->roles)) ? 'Tesorería' : 'Comunicación'; ?></td>
                        <td><a href="?page=montseny-equipo&action=delete&user_id=<?php echo $u->ID; ?>" style="color:#a00;" onclick="return confirm('¿Eliminar?')">Quitar</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
