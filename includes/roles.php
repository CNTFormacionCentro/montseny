<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registro de Roles
 */
add_action('init', function() {
    if ( ! get_role( 'montseny_tesorero' ) ) {
        add_role( 'montseny_tesorero', 'Tesorería Montseny', array( 'read' => true ) );
    }
    if ( ! get_role( 'montseny_comunica' ) ) {
        add_role( 'montseny_comunica', 'Comunicación Montseny', array( 'read' => true ) );
    }
    if ( ! get_role( 'afiliade' ) ) {
        add_role( 'afiliade', 'Afiliade Montseny', array( 'read' => true ) );
    }
});

/**
 * Menús del Backend
 */
add_action( 'admin_menu', function() {
    add_menu_page( 'Montseny', 'Montseny', 'manage_options', 'montseny-dashboard', 'montseny_settings_page', 'dashicons-shield-alt', 2 );
    add_submenu_page( 'montseny-dashboard', 'Equipo / Cargos', 'Equipo / Cargos', 'manage_options', 'montseny-equipo', 'montseny_equipo_admin' );
});

/**
 * PÁGINA: CONFIGURACIÓN GENERAL
 */
function montseny_settings_page() {
    if (isset($_POST['m_save_settings'])) {
        update_option('montseny_nombre_local', sanitize_text_field($_POST['l_name']));
        update_option('montseny_url_local', esc_url_raw($_POST['u_local']));
        update_option('montseny_url_confederal', esc_url_raw($_POST['u_conf']));
        update_option('montseny_telegram_alias', sanitize_text_field($_POST['t_alias']));
        echo '<div class="updated"><p>Configuración guardada correctamente.</p></div>';
    }
    $l = get_option('montseny_nombre_local', 'CNT Ciudad Real');
    $u_l = get_option('montseny_url_local', 'https://ciudadreal.cnt.es');
    $u_c = get_option('montseny_url_confederal', 'https://www.cnt.es');
    $t = get_option('montseny_telegram_alias', 'cnt_nacional');
    ?>
    <div class="wrap">
        <h1>Configuración Montseny</h1>
        <form method="post" style="background:#fff; padding:20px; border:1px solid #ccc; max-width:600px; margin-top:20px;">
            <p><label><strong>Nombre del Sindicato:</strong></label><br>
            <input type="text" name="l_name" value="<?php echo esc_attr($l); ?>" class="regular-text"></p>
            
            <p><label><strong>URL Web Local:</strong></label><br>
            <input type="url" name="u_local" value="<?php echo esc_attr($u_l); ?>" class="regular-text"></p>
            
            <p><label><strong>URL Web Confederal:</strong></label><br>
            <input type="url" name="u_conf" value="<?php echo esc_attr($u_c); ?>" class="regular-text"></p>
            
            <p><label><strong>Alias Telegram (sin @):</strong></label><br>
            <input type="text" name="t_alias" value="<?php echo esc_attr($t); ?>" class="regular-text"></p>
            
            <input type="submit" name="m_save_settings" class="button button-primary" value="Guardar Cambios">
        </form>
    </div>
    <?php
}

/**
 * PÁGINA: GESTIÓN DE EQUIPO (ASIGNAR CARGOS)
 */
function montseny_equipo_admin() {
    // Revocar cargo (vuelve a ser afiliado normal)
    if (isset($_GET['action']) && $_GET['action'] == 'remove_role') {
        $u = new WP_User(intval($_GET['user_id']));
        $u->set_role('afiliade');
        echo '<div class="updated"><p>Cargo revocado.</p></div>';
    }

    // Asignar cargo a un afiliado existente
    if (isset($_POST['promote_afiliado'])) {
        $u = new WP_User(intval($_POST['user_to_promote']));
        $u->set_role($_POST['new_role']);
        echo '<div class="updated"><p>Cargo asignado con éxito.</p></div>';
    }

    // Listas para el formulario
    $todos_los_afiliados = get_users(array('role' => 'afiliade'));
    $equipo_actual = get_users(array('role__in' => array('montseny_tesorero', 'montseny_comunica')));
    ?>
    <div class="wrap">
        <h1>Cargos y Responsabilidades</h1>
        <p>Los responsables deben ser afiliados previamente creados.</p>

        <div style="display:flex; gap:20px; margin-top:20px;">
            <!-- FORMULARIO ASIGNACIÓN -->
            <div style="background:#fff; padding:20px; border:1px solid #ccc; width:350px;">
                <h3>Asignar Nuevo Cargo</h3>
                <form method="post">
                    <label>Seleccionar Afiliade:</label><br>
                    <select name="user_to_promote" style="width:100%; margin-bottom:15px;">
                        <?php foreach($todos_los_afiliados as $a): ?>
                            <option value="<?php echo $a->ID; ?>"><?php echo $a->display_name; ?> (<?php echo $a->user_email; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label>Cargo / Responsabilidad:</label><br>
                    <select name="new_role" style="width:100%; margin-bottom:20px;">
                        <option value="montseny_tesorero">Tesorería</option>
                        <option value="montseny_comunica">Comunicación</option>
                    </select>
                    
                    <input type="submit" name="promote_afiliado" class="button button-primary" value="Asignar Responsabilidad">
                </form>
            </div>

            <!-- TABLA EQUIPO -->
            <div style="flex:1;">
                <h3>Responsables en activo</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Nombre</th><th>Cargo</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach($equipo_actual as $st): ?>
                        <tr>
                            <td><strong><?php echo $st->display_name; ?></strong></td>
                            <td><?php 
                                if(in_array('montseny_tesorero', $st->roles)) echo 'Tesorería';
                                elseif(in_array('montseny_comunica', $st->roles)) echo 'Comunicación';
                            ?></td>
                            <td><a href="?page=montseny-equipo&action=remove_role&user_id=<?php echo $st->ID; ?>" style="color:#a00;">Quitar Cargo</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($equipo_actual)): ?><tr><td colspan="3">No hay cargos asignados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
