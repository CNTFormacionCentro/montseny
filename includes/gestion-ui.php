<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_render_gestion() {
    global $wpdb; 
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $ramos_cnt = ["Oficios Varios", "Metal, Minería y Química", "Construcción y Madera", "Transportes y Comunicaciones", "Comercio, Hostelería y Alimentación", "Sanidad, Acción Social, Enseñanza y Cultura", "Administración y Servicios Públicos", "Artes Gráficas, Papel y Espectáculos", "Limpieza, Mantenimiento y Servicios Auxiliares", "Agroalimentario"];

    // --- ACCIÓN: GUARDAR NUEVO AFILIADO (ALTA MANUAL) ---
    if (isset($_POST['m_alta_manual'])) {
        $email = sanitize_email($_POST['em']);
        $pass = $_POST['pw'];
        
        $uid = wp_create_user($email, $pass, $email);
        
        if (is_wp_error($uid)) {
            $error_msg = $uid->get_error_message();
        } else {
            wp_update_user(['ID' => $uid, 'display_name' => sanitize_text_field($_POST['no'])]);
            (new WP_User($uid))->set_role('afiliade');
            
            $wpdb->insert($tabla, [
                'user_id' => $uid,
                'situacion' => 'Alta',
                'codigo_interno' => sanitize_text_field($_POST['co']),
                'genero' => $_POST['ge'],
                'telefono' => sanitize_text_field($_POST['te']),
                'ramo' => $_POST['ra'],
                'direccion_cifrada' => Montseny_Crypto::encrypt($_POST['di']),
                'iban_cifrado' => Montseny_Crypto::encrypt($_POST['ib']),
                'etiquetas' => sanitize_text_field($_POST['ta']),
                'observaciones' => sanitize_textarea_field($_POST['ob']),
                'fecha_alta' => date('Y-m-d')
            ]);
            wp_redirect(site_url('/montseny/gestion/?msg=ok')); exit;
        }
    }

    // --- ACCIÓN: GUARDAR EDICIÓN ---
    if (isset($_POST['save_edit'])) {
        $uid = intval($_POST['uid']);
        wp_update_user(['ID' => $uid, 'user_email' => sanitize_email($_POST['em']), 'display_name' => sanitize_text_field($_POST['no'])]);
        if(!empty($_POST['pw'])) wp_set_password($_POST['pw'], $uid);

        $wpdb->update($tabla, [
            'codigo_interno' => sanitize_text_field($_POST['co']),
            'genero' => $_POST['ge'],
            'telefono' => sanitize_text_field($_POST['te']),
            'ramo' => $_POST['ra'],
            'etiquetas' => sanitize_text_field($_POST['ta']),
            'observaciones' => sanitize_textarea_field($_POST['ob']),
            'direccion_cifrada' => Montseny_Crypto::encrypt($_POST['di']),
            'iban_cifrado' => Montseny_Crypto::encrypt($_POST['ib'])
        ], ['user_id' => $uid]);
        wp_redirect(site_url('/montseny/gestion/?msg=ok')); exit;
    }

    // --- ACCIÓN: CAMBIAR SITUACIÓN (ALTA/BAJA) ---
    if (isset($_GET['toggle_status']) && isset($_GET['uid'])) {
        $new_status = ($_GET['toggle_status'] == 'Alta') ? 'Baja' : 'Alta';
        $wpdb->update($tabla, ['situacion' => $new_status], ['user_id' => intval($_GET['uid'])]);
        wp_redirect(site_url('/montseny/gestion/?ver='.$_GET['toggle_status'])); exit;
    }

    // --- LÓGICA DE LISTADO ---
    $filtro = isset($_GET['ver']) ? sanitize_text_field($_GET['ver']) : 'Alta';
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.display_name, a.codigo_interno, a.situacion 
         FROM {$wpdb->users} u 
         INNER JOIN $tabla a ON u.ID = a.user_id 
         WHERE a.situacion = %s ORDER BY a.id DESC", $filtro
    ));
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #111; color: #fff; margin: 0; }
        .bar { background: #333; padding: 15px; text-align: center; border-bottom: 2px solid #CC0000; font-weight:bold; }
        .container { padding: 15px; }
        .btn { background: #CC0000; color: #fff; padding: 12px; border-radius: 6px; text-decoration: none; border:none; font-weight:bold; cursor:pointer; display:inline-block; width:100%; box-sizing:border-box; text-align:center;}
        .btn-sm { width: auto; padding: 6px 12px; font-size: 0.75rem; background: #444; margin-left:5px; }
        .item { background: #222; padding: 12px; border-radius: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid #CC0000; }
        .tabs { display: flex; gap: 5px; margin-bottom: 20px; }
        .tab { flex: 1; padding: 12px; text-align: center; background: #222; text-decoration: none; color: #888; border-radius: 6px; font-size: 0.8rem; font-weight:bold; border: 1px solid #333; }
        .tab.active { background: #CC0000; color: #fff; border-color: #CC0000; }
        input, select, textarea { width: 100%; padding: 12px; margin: 8px 0; background: #333; color: #fff; border: 1px solid #444; border-radius: 5px; box-sizing: border-box; }
        label { color: #CC0000; font-weight: bold; font-size: 0.75rem; text-transform: uppercase; display:block; margin-top:10px;}
        .msg-ok { background: #004400; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; font-weight: bold; }
        .msg-err { background: #600; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
    </style></head><body>
    <div class="bar">GESTIÓN MONTSENY</div>
    <div class="container">

        <?php if(isset($_GET['msg'])) echo '<div class="msg-ok">✅ Operación realizada</div>'; ?>
        <?php if(isset($error_msg)) echo '<div class="msg-err">❌ '.$error_msg.'</div>'; ?>

        <div class="tabs">
            <a href="?ver=Alta" class="tab <?php echo ($filtro=='Alta')?'active':''; ?>">ACTIVOS</a>
            <a href="?ver=Baja" class="tab <?php echo ($filtro=='Baja')?'active':''; ?>">BAJAS</a>
        </div>

        <?php if (isset($_GET['view']) && $_GET['view'] == 'alta') : ?>
            <h3>Nuevo Afiliado</h3>
            <form method="post">
                <input type="text" name="no" placeholder="Nombre completo" required>
                <input type="email" name="em" placeholder="Email (Login App)" required>
                <input type="text" name="pw" placeholder="Contraseña App" required>
                <input type="text" name="co" placeholder="Código Carnet (F018...)">
                <select name="ra"><option value="">Seleccionar Ramo...</option><?php foreach($ramos_cnt as $r) echo "<option value='$r'>$r</option>"; ?></select>
                <select name="ge"><option value="">Género</option><option>Hombre</option><option>Mujer</option><option>Otro</option></select>
                <input type="text" name="te" placeholder="Teléfono">
                <input type="text" name="ib" placeholder="IBAN">
                <input type="text" name="di" placeholder="Dirección">
                <textarea name="ta" placeholder="Etiquetas (separadas por comas)"></textarea>
                <textarea name="ob" placeholder="Observaciones"></textarea>
                <button type="submit" name="m_alta_manual" class="btn">CREAR AFILIADO</button>
                <a href="?" class="btn" style="background:#444; margin-top:10px;">CANCELAR</a>
            </form>

        <?php elseif (isset($_GET['edit'])) : 
            $uid = intval($_GET['edit']); 
            $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $uid)); 
            $u = get_userdata($uid);
        ?>
            <h3>Editar: <?php echo $u->display_name; ?></h3>
            <form method="post">
                <input type="hidden" name="uid" value="<?php echo $uid; ?>">
                <label>Nombre</label><input type="text" name="no" value="<?php echo $u->display_name; ?>">
                <label>Email</label><input type="email" name="em" value="<?php echo $u->user_email; ?>">
                <label>Password (vaciá para no cambiar)</label><input type="text" name="pw" placeholder="Nueva clave">
                <label>Código</label><input type="text" name="co" value="<?php echo $c->codigo_interno; ?>">
                <label>Ramo</label>
                <select name="ra"><?php foreach($ramos_cnt as $r) echo "<option value='$r' ".($c->ramo==$r?'selected':'').">$r</option>"; ?></select>
                <label>IBAN</label><input type="text" name="ib" value="<?php echo Montseny_Crypto::decrypt($c->iban_cifrado); ?>">
                <label>Dirección</label><input type="text" name="di" value="<?php echo Montseny_Crypto::decrypt($c->direccion_cifrada); ?>">
                <button type="submit" name="save_edit" class="btn">GUARDAR CAMBIOS</button>
                <a href="?" class="btn" style="background:#444; margin-top:10px;">VOLVER</a>
            </form>

        <?php else : ?>
            <a href="?view=alta" class="btn" style="margin-bottom:20px;">➕ AÑADIR A MANO</a>
            <h3>Listado: <?php echo count($results); ?> compas</h3>
            <?php foreach($results as $af): ?>
                <div class="item">
                    <span><strong><?php echo $af->display_name; ?></strong><br><small><?php echo $af->codigo_interno; ?></small></span>
                    <div style="display:flex;">
                        <a href="?edit=<?php echo $af->ID; ?>" class="btn btn-sm">EDITAR</a>
                        <a href="?uid=<?php echo $af->ID; ?>&toggle_status=<?php echo $af->situacion; ?>" class="btn btn-sm" style="background:#600;"><?php echo ($af->situacion=='Alta')?'BAJA':'ALTA'; ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p style="text-align:center; margin-top:30px;"><a href="<?php echo site_url('/montseny'); ?>" style="color:#666; text-decoration:none; font-size:0.8rem;">← APP</a></p>
    </div></body></html>
    <?php
}
