<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_render_gestion() {
    global $wpdb; $tabla = $wpdb->prefix . 'montseny_afiliados';
    $ramos_cnt = ["Oficios Varios", "Metal, Minería y Química", "Construcción y Madera", "Transportes y Comunicaciones", "Comercio, Hostelería y Alimentación", "Sanidad, Acción Social, Enseñanza y Cultura", "Administración y Servicios Públicos", "Artes Gráficas, Papel y Espectáculos", "Limpieza, Mantenimiento y Servicios Auxiliares", "Agroalimentario"];

    // --- ACCIÓN: GUARDAR ALTA MANUAL / REPARAR ---
    if (isset($_POST['m_alta_manual'])) {
        $email = sanitize_email($_POST['em']);
        $existing_user = get_user_by('email', $email);
        
        if ($existing_user) {
            $uid = $existing_user->ID;
        } else {
            $uid = wp_create_user($email, $_POST['pw'], $email);
        }

        if (!is_wp_error($uid)) {
            wp_update_user(['ID' => $uid, 'display_name' => sanitize_text_field($_POST['no'])]);
            $u = new WP_User($uid); $u->set_role('afiliade');
            
            // Borramos si existía algo viejo para este ID y creamos nuevo registro limpio
            $wpdb->delete($tabla, ['user_id' => $uid]);
            $wpdb->insert($tabla, [
                'user_id' => $uid, 'situacion' => 'Alta', 'codigo_interno' => sanitize_text_field($_POST['co']),
                'genero' => $_POST['ge'], 'telefono' => $_POST['te'], 'ramo' => $_POST['ra'],
                'direccion_cifrada' => Montseny_Crypto::encrypt($_POST['di']), 'iban_cifrado' => Montseny_Crypto::encrypt($_POST['ib']),
                'fecha_alta' => date('Y-m-d'), 'observaciones' => $_POST['ob']
            ]);
            wp_redirect(site_url('/montseny/gestion/?msg=ok')); exit;
        }
    }

    // --- ACCIÓN: CAMBIAR SITUACIÓN (ALTA/BAJA) ---
    if (isset($_GET['toggle_status']) && isset($_GET['uid'])) {
        $new_status = ($_GET['toggle_status'] == 'Alta') ? 'Baja' : 'Alta';
        $wpdb->update($tabla, ['situacion' => $new_status], ['user_id' => intval($_GET['uid'])]);
        wp_redirect(site_url('/montseny/gestion/')); exit;
    }

    // --- LÓGICA DE FILTRO Y LISTADO ---
    $filtro = isset($_GET['ver']) ? sanitize_text_field($_GET['ver']) : 'Alta';
    // Buscamos todos los usuarios que son 'afiliade' en WordPress
    $wp_users = get_users(['role' => 'afiliade']);
    $afiliados_final = [];

    foreach ($wp_users as $u) {
        $extra = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $u->ID));
        $situacion = ($extra) ? $extra->situacion : 'Alta';
        
        // Solo añadimos a la lista si coincide con el filtro (Alta o Baja)
        if ($situacion == $filtro) {
            $afiliados_final[] = [
                'ID' => $u->ID,
                'nombre' => $u->display_name,
                'email' => $u->user_email,
                'codigo' => ($extra) ? $extra->codigo_interno : 'PENDIENTE',
                'situacion' => $situacion
            ];
        }
    }

    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #111; color: #fff; margin: 0; }
        .bar { background: #333; padding: 15px; text-align: center; border-bottom: 2px solid #CC0000; font-weight:bold; }
        .container { padding: 15px; }
        .btn { background: #CC0000; color: #fff; padding: 10px; border-radius: 6px; text-decoration: none; border:none; font-weight:bold; cursor:pointer; display:inline-block; width:100%; box-sizing:border-box; text-align:center;}
        .btn-sm { width: auto; padding: 5px 10px; font-size: 0.75rem; background: #444; }
        .item { background: #222; padding: 12px; border-radius: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid #CC0000; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab { flex: 1; padding: 10px; text-align: center; background: #333; text-decoration: none; color: #888; border-radius: 5px; font-weight: bold; }
        .tab.active { background: #CC0000; color: #fff; }
        input, select, textarea { width: 100%; padding: 12px; margin: 8px 0; background: #333; color: #fff; border: 1px solid #444; border-radius: 5px; box-sizing: border-box; }
    </style></head><body>
    <div class="bar">GESTIÓN MONTSENY</div>
    <div class="container">

        <div class="tabs">
            <a href="?ver=Alta" class="tab <?php echo ($filtro=='Alta')?'active':''; ?>">ACTIVOS (ALTA)</a>
            <a href="?ver=Baja" class="tab <?php echo ($filtro=='Baja')?'active':''; ?>">BAJAS</a>
        </div>

        <?php if (isset($_GET['view']) && $_GET['view'] == 'alta') : ?>
            <form method="post">
                <h3>Nuevo Afiliado / Reparar</h3>
                <input type="text" name="no" placeholder="Nombre completo" required>
                <input type="email" name="em" placeholder="Email (Login)" required>
                <input type="text" name="pw" placeholder="Contraseña App" required>
                <input type="text" name="co" placeholder="Código F018...">
                <select name="ra"><?php foreach($ramos_cnt as $r) echo "<option value='$r'>$r</option>"; ?></select>
                <input type="text" name="ib" placeholder="IBAN">
                <input type="text" name="di" placeholder="Dirección">
                <button type="submit" name="m_alta_manual" class="btn">GRABAR AFILIADO</button>
                <a href="?" class="btn" style="background:#444; margin-top:10px;">CANCELAR</a>
            </form>
        <?php else : ?>
            <a href="?view=alta" class="btn" style="margin-bottom:20px;">➕ AÑADIR NUEVO</a>
            
            <h3>Listado: <?php echo $filtro; ?>s (<?php echo count($afiliados_final); ?>)</h3>
            <?php foreach($afiliados_final as $af): ?>
                <div class="item">
                    <span><strong><?php echo $af['nombre']; ?></strong><br><small><?php echo $af['codigo']; ?></small></span>
                    <div style="display:flex; gap:5px;">
                        <a href="?edit=<?php echo $af['ID']; ?>" class="btn btn-sm">EDITAR</a>
                        <a href="?uid=<?php echo $af['ID']; ?>&toggle_status=<?php echo $af['situacion']; ?>" class="btn btn-sm" style="background:#600;">
                            <?php echo ($af['situacion']=='Alta')?'DAR BAJA':'DAR ALTA'; ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <br><a href="<?php echo site_url('/montseny'); ?>" style="color:#666; display:block; text-align:center;">← Salir a la App</a>
    </div></body></html>
    <?php
}
