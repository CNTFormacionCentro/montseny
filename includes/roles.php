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
        <h1>Configuración Montseny</h1>
        <form method="post">
            <p><label>Nombre Sindicato:</label><br><input type="text" name="l_name" value="<?php echo $l; ?>"></p>
            <p><label>Alias Telegram:</label><br><input type="text" name="t_alias" value="<?php echo $t; ?>"></p>
            <input type="submit" name="m_save_settings" class="button button-primary" value="Guardar">
        </form>
    </div>
    <?php
}

// PÁGINA DE EQUIPO (Promocionar Afiliados a Cargos)
function montseny_equipo_admin() {
    if (isset($_GET['action']) && $_GET['action'] == 'remove_role') {
        $u = new WP_User(intval($_GET['user_id']));
        $u->set_role('afiliade'); // Vuelve a ser afiliado normal
        echo '<div class="updated"><p>Cargo revocado.</p></div>';
    }

    if (isset($_POST['promote_afiliado'])) {
        $u = new WP_User(intval($_POST['user_id']));
        $u->set_role($_POST['ro']); // Le asignamos el cargo
        echo '<div class="updated"><p>'.$u->display_name.' ahora tiene el cargo asignado.</p></div>';
    }

    $afiliados_nomales = get_users(array('role' => 'afiliade'));
    $equipo = get_users(array('role__in' => array('montseny_tesorero', 'montseny_comunica')));
    ?>
    <div class="wrap">
        <h1>Cargos de Gestión</h1>
        <p>Selecciona un afiliado existente para asignarle una responsabilidad.</p>
        
        <div style="display:flex; gap:20px; margin-top:20px;">
            <!-- FORMULARIO: ELEGIR AFILIADO -->
            <form method="post" style="background:#fff; padding:20px; border:1px solid #ccc; width:300px;">
                <h3>Asignar Responsabilidad</h3>
                <label>Elegir Afiliade:</label><br>
                <select name="user_id" style="width:100%; margin-bottom:15px;">
                    <?php foreach($afiliados_nomales as $a): ?>
                        <option value="<?php echo $a->ID; ?>"><?php echo $a->display_name; ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Cargo:</label><br>
                <select name="ro" style="width:100%; margin-bottom:15px;">
                    <option value="montseny_tesorero">Tesorería</option>
                    <option value="montseny_comunica">Comunicación</option>
                </select>
                <input type="submit" name="promote_afiliado" class="button button-primary" value="Asignar Cargo">
            </form>

            <!-- TABLA: CARGOS ACTUALES -->
            <table class="wp-list-table widefat fixed striped" style="flex:1;">
                <thead><tr><th>Nombre</th><th>Cargo Actual</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach($equipo as $u): ?>
                    <tr>
                        <td><strong><?php echo $u->display_name; ?></strong></td>
                        <td><?php echo (in_array('montseny_tesorero', $u->roles)) ? 'Tesorería' : 'Comunicación'; ?></td>
                        <td><a href="?page=montseny-equipo&action=remove_role&user_id=<?php echo $u->ID; ?>" style="color:#a00;">Quitar Cargo</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
