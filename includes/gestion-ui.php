<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Obtener etiquetas únicas para sugerencias
 */
function montseny_get_tags_suggestions() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $res = $wpdb->get_col("SELECT etiquetas FROM $tabla WHERE etiquetas != ''");
    $tags = [];
    foreach ($res as $row) {
        $parts = explode(',', $row);
        foreach ($parts as $p) {
            $t = trim($p);
            if (!empty($t) && !in_array($t, $tags)) $tags[] = $t;
        }
    }
    return $tags;
}

function montseny_render_gestion() {
    global $wpdb; 
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $ramos_cnt = ["Metal, Minería y Química", "Construcción y Madera", "Transportes y Comunicaciones", "Comercio, Hostelería y Alimentación", "Sanidad, Acción Social, Enseñanza y Cultura", "Administración y Servicios Públicos", "Artes Gráficas, Papel y Espectáculos", "Limpieza, Mantenimiento y Servicios Auxiliares", "Agroalimentario", "Oficios Varios"];

    // --- ACCIÓN: GUARDAR NUEVO / EDITAR ---
    if (isset($_POST['m_save_afiliado'])) {
        $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;
        $email = sanitize_email($_POST['em']);
        
        if ($uid === 0) {
            $uid = wp_create_user($email, $_POST['pw'], $email);
        } else {
            wp_update_user(['ID' => $uid, 'user_email' => $email]);
            if(!empty($_POST['pw'])) wp_set_password($_POST['pw'], $uid);
        }

        if (!is_wp_error($uid)) {
            wp_update_user(['ID' => $uid, 'display_name' => sanitize_text_field($_POST['no'])]);
            (new WP_User($uid))->set_role('afiliade');
            
            $datos = [
                'user_id'           => $uid,
                'codigo_interno'    => sanitize_text_field($_POST['co']),
                'genero'            => $_POST['ge'],
                'telefono'          => sanitize_text_field($_POST['te']),
                'fecha_nacimiento'  => sanitize_text_field($_POST['fn']),
                'ramo'              => $_POST['ra'],
                'direccion_cifrada' => Montseny_Crypto::encrypt($_POST['di']),
                'iban_cifrado'      => Montseny_Crypto::encrypt($_POST['ib']),
                'etiquetas'         => sanitize_text_field($_POST['ta']),
                'observaciones'     => sanitize_textarea_field($_POST['ob']),
                'situacion'         => $_POST['si']
            ];

            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tabla WHERE user_id = %d", $uid));
            if ($exists) {
                $wpdb->update($tabla, $datos, ['user_id' => $uid]);
            } else {
                $datos['fecha_alta'] = date('Y-m-d');
                $wpdb->insert($tabla, $datos);
            }
            wp_redirect(site_url('/montseny/gestion/?msg=ok')); exit;
        }
    }

    // --- LÓGICA DE LISTADO Y FILTRO ---
    $filtro = (isset($_GET['ver']) && $_GET['ver'] == 'Baja') ? 'Baja' : 'Alta';
    // Buscamos usuarios con cualquier rol del sindicato que coincidan con la situación
    $query = "SELECT u.ID, u.display_name, a.codigo_interno 
              FROM {$wpdb->users} u 
              INNER JOIN $tabla a ON u.ID = a.user_id 
              WHERE a.situacion = %s ORDER BY a.id DESC";
    $afiliados = $wpdb->get_results($wpdb->prepare($query, $filtro));
    $tag_suggestions = montseny_get_tags_suggestions();
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #111; color: #fff; margin: 0; padding: 15px; }
        .bar { background: #333; padding: 15px; text-align: center; border-bottom: 2px solid #CC0000; margin: -15px -15px 20px -15px; font-weight:bold; }
        .btn { background: #CC0000; color: #fff; padding: 12px; border-radius: 6px; text-decoration: none; border:none; font-weight:bold; width:100%; display:block; text-align:center; cursor:pointer; box-sizing:border-box;}
        .tabs { display: flex; gap: 5px; margin-bottom: 20px; }
        .tab { flex: 1; padding: 10px; text-align: center; background: #222; text-decoration: none; color: #888; border-radius: 5px; font-size: 0.8rem; border: 1px solid #333; }
        .tab.active { background: #CC0000; color: #fff; }
        .item { background: #222; padding: 12px; border-radius: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid #CC0000; }
        input, select, textarea { width: 100%; padding: 12px; margin: 8px 0; background: #333; color: #fff; border: 1px solid #444; border-radius: 5px; box-sizing: border-box; }
        label { color: #CC0000; font-weight: bold; font-size: 0.7rem; text-transform: uppercase; display:block; margin-top:10px;}
    </style></head><body>
    <div class="bar">GESTIÓN MONTSENY</div>

    <?php if (isset($_GET['edit']) || (isset($_GET['view']) && $_GET['view'] == 'alta')) : 
        $uid = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $c = $uid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $uid)) : null;
        $u = $uid ? get_userdata($uid) : null;
    ?>
        <form method="post">
            <?php if($uid): ?><input type="hidden" name="uid" value="<?php echo $uid; ?>"><?php endif; ?>
            
            <label>Nombre y Apellidos</label><input type="text" name="no" value="<?php echo $u ? esc_attr($u->display_name) : ''; ?>" required>
            <label>Email (Login)</label><input type="email" name="em" value="<?php echo $u ? esc_attr($u->user_email) : ''; ?>" required>
            <label>Password</label><input type="text" name="pw" placeholder="<?php echo $uid ? 'Vaciá para no cambiar' : 'Clave obligatoria'; ?>" <?php echo $uid ? '' : 'required'; ?>>
            
            <label>Código Interno (F018...)</label><input type="text" name="co" value="<?php echo $c ? esc_attr($c->codigo_interno) : ''; ?>">
            
            <label>Ramo CNT</label>
            <select name="ra">
                <option value="">Seleccionar Ramo...</option>
                <?php foreach($ramos_cnt as $r) echo "<option value='$r' ".($c && $c->ramo==$r?'selected':'').">$r</option>"; ?>
            </select>

            <label>Situación</label>
            <select name="si">
                <option value="Alta" <?php echo ($c && $c->situacion=='Alta')?'selected':''; ?>>Alta (Activo)</option>
                <option value="Baja" <?php echo ($c && $c->situacion=='Baja')?'selected':''; ?>>Baja (Inactivo)</option>
            </select>

            <label>Género / Teléfono</label>
            <div style="display:flex; gap:10px;">
                <select name="ge" style="width:40%"><option value="">Género...</option><option <?php echo ($c && $c->genero=='Hombre')?'selected':''; ?>>Hombre</option><option <?php echo ($c && $c->genero=='Mujer')?'selected':''; ?>>Mujer</option><option <?php echo ($c && $c->genero=='Otro')?'selected':''; ?>>Otro</option></select>
                <input type="text" name="te" value="<?php echo $c ? esc_attr($c->telefono) : ''; ?>" placeholder="Teléfono" style="width:60%">
            </div>

            <label>Fecha Nacimiento</label><input type="text" name="fn" value="<?php echo $c ? esc_attr($c->fecha_nacimiento) : ''; ?>" placeholder="DD/MM/AAAA">
            <label>IBAN (Cifrado)</label><input type="text" name="ib" value="<?php echo $c ? Montseny_Crypto::decrypt($c->iban_cifrado) : ''; ?>">
            <label>Dirección (Cifrada)</label><input type="text" name="di" value="<?php echo $c ? Montseny_Crypto::decrypt($c->direccion_cifrada) : ''; ?>">

            <label>Etiquetas</label>
            <input type="text" name="ta" list="tags-list" value="<?php echo $c ? esc_attr($c->etiquetas) : ''; ?>" placeholder="Empresa, aficiones...">
            <datalist id="tags-list">
                <?php foreach($tag_suggestions as $ts) echo "<option value='$ts'>"; ?>
            </datalist>

            <label>Observaciones</label>
            <textarea name="ob" rows="4"><?php echo $c ? esc_textarea($c->observaciones) : ''; ?></textarea>

            <button type="submit" name="m_save_afiliado" class="btn">GUARDAR CAMBIOS</button>
            <a href="?" style="color:#888; display:block; text-align:center; margin-top:15px; text-decoration:none;">← Volver</a>
        </form>
    <?php else : ?>
        <div class="tabs">
            <a href="?ver=Alta" class="tab <?php echo ($filtro=='Alta')?'active':''; ?>">ACTIVOS (<?php echo ($filtro=='Alta')?count($afiliados):'...'; ?>)</a>
            <a href="?ver=Baja" class="tab <?php echo ($filtro=='Baja')?'active':''; ?>">BAJAS (<?php echo ($filtro=='Baja')?count($afiliados):'...'; ?>)</a>
        </div>
        <a href="?view=alta" class="btn">➕ NUEVO AFILIADO</a>
        
        <h3 style="color:#CC0000; margin-top:25px;">Lista de Afiliación</h3>
        <?php foreach($afiliados as $af): ?>
            <div class="item">
                <span><strong><?php echo $af->display_name; ?></strong><br><small style="color:#aaa;"><?php echo $af->codigo_interno; ?></small></span>
                <a href="?edit=<?php echo $af->ID; ?>" style="color:#CC0000; font-weight:bold; text-decoration:none; background:#333; padding:5px 10px; border-radius:4px;">EDITAR</a>
            </div>
        <?php endforeach; ?>
        <?php if(empty($afiliados)): ?><p>No hay afiliados en esta sección.</p><?php endif; ?>
        
        <a href="<?php echo site_url('/montseny'); ?>" style="color:#666; display:block; text-align:center; margin-top:30px; font-size:0.8rem; text-decoration:none;">← SALIR</a>
    <?php endif; ?>
    </body></html>
    <?php
}
