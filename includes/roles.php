<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CONFIGURACIÓN DE ROLES AL ACTIVAR
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
 * MENÚ EN EL BACKEND DE WORDPRESS
 */
add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_equipo_admin', 'dashicons-shield-alt', 2 );
});

function montseny_equipo_admin() {
    // Lógica para borrar a alguien
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['user_id'])) {
        wp_delete_user(intval($_GET['user_id']));
        echo '<div class="updated"><p>Acceso eliminado.</p></div>';
    }

    // Lógica para añadir a alguien
    if (isset($_POST['add_eq'])) {
        $email = sanitize_email($_POST['em']);
        $uid = wp_create_user($email, $_POST['ps'], $email);
        if (!is_wp_error($uid)) {
            wp_update_user(array('ID'=>$uid, 'display_name'=>sanitize_text_field($_POST['no']), 'role'=>$_POST['ro']));
            echo '<div class="updated"><p>¡Compañere añadido al equipo!</p></div>';
        } else {
            echo '<div class="error"><p>'.$uid->get_error_message().'</p></div>';
        }
    }

    $equipo = get_users(array('role__in' => array('montseny_tesorero', 'montseny_comunica')));
    ?>
    <div class="wrap">
        <h1>Gestión de Equipo Montseny</h1>
        <div style="display: flex; gap: 30px; margin-top:20px;">
            <!-- FORMULARIO -->
            <form method="post" style="background:#fff; padding:20px; border:1px solid #ccc; max-width:350px;">
                <h3>Añadir Acceso</h3>
                <input type="text" name="no" placeholder="Nombre completo" required style="width:100%; margin-bottom:10px;"><br>
                <input type="email" name="em" placeholder="Email" required style="width:100%; margin-bottom:10px;"><br>
                <input type="text" name="ps" placeholder="Contraseña App" required style="width:100%; margin-bottom:10px;"><br>
                <select name="ro" style="width:100%; margin-bottom:15px;">
                    <option value="montseny_tesorero">Tesorería (Importa y Edita)</option>
                    <option value="montseny_comunica">Comunicación (Noticias)</option>
                </select>
                <input type="submit" name="add_eq" class="button button-primary" value="Crear Cuenta de Equipo">
            </form>

            <!-- LISTA -->
            <div style="flex-grow:1;">
                <h3>Responsables Actuales</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach($equipo as $user): ?>
                        <tr>
                            <td><strong><?php echo $user->display_name; ?></strong></td>
                            <td><?php echo $user->user_email; ?></td>
                            <td><?php echo (in_array('montseny_tesorero', $user->roles)) ? 'Tesorería' : 'Comunicación'; ?></td>
                            <td><a href="?page=montseny-dashboard&action=delete&user_id=<?php echo $user->ID; ?>" class="button button-link-delete" style="color:#a00;" onclick="return confirm('¿Seguro que quieres quitar el acceso?')">Eliminar</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
