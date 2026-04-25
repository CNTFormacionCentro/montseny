<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Función auxiliar para obtener todas las etiquetas únicas ya creadas
 */
function montseny_get_existing_tags() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $raw_tags = $wpdb->get_col("SELECT etiquetas FROM $tabla WHERE etiquetas != ''");
    $final_tags = [];
    foreach ($raw_tags as $line) {
        $parts = explode(',', $line);
        foreach ($parts as $p) {
            $tag = trim($p);
            if (!empty($tag) && !in_array($tag, $final_tags)) {
                $final_tags[] = $tag;
            }
        }
    }
    sort($final_tags);
    return $final_tags;
}

function montseny_render_gestion() {
    global $wpdb; 
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $ramos_cnt = ["Oficios Varios", "Metal, Minería y Química", "Construcción y Madera", "Transportes y Comunicaciones", "Comercio, Hostelería y Alimentación", "Sanidad, Acción Social, Enseñanza y Cultura", "Administración y Servicios Públicos", "Artes Gráficas, Papel y Espectáculos", "Limpieza, Mantenimiento y Servicios Auxiliares", "Agroalimentario"];
    
    $existing_tags = montseny_get_existing_tags();

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

    // --- LÓGICA DE LISTADO ---
    $filtro = (isset($_GET['ver']) && $_GET['ver'] == 'Baja') ? 'Baja' : 'Alta';
    $afiliados = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.display_name, a.codigo_interno FROM {$wpdb->users} u 
         INNER JOIN $tabla a ON u.ID = a.user_id WHERE a.situacion = %s ORDER BY a.id DESC", $filtro
    ));
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
        input, select, textarea { width: 100%; padding: 12px; margin: 8px 0; background: #333; color: #fff; border: 1px solid #444; border-radius: 5px; box-sizing: border-box; font-size: 1rem;}
        label { color: #CC0000; font-weight: bold; font-size: 0.7rem; text-transform: uppercase; display:block; margin-top:10px;}
        .hint { color: #666; font-size: 0.7rem; margin-top: -5px; margin-bottom: 10px; }
    </style></head><body>
    <div class="bar">GESTIÓN MONTSENY</div>

    <?php if (isset($_GET['edit']) || (isset($_GET['view']) && $_GET['view'] == 'alta')) : 
        $uid = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $c = $uid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $uid)) : null;
        $u = $uid ? get_userdata($uid) : null;
    ?>
        <form method="post">
            <?php if($uid): ?><input type="hidden" name="uid" value="<?php echo $uid; ?>"><?php endif; ?>
            
            <label>Nombre Completo</label><input type="text" name="no" value="<?php echo $u ? $u->display_name : ''; ?>" required>
            <label>Email (Login)</label><input type="email" name="em" value="<?php echo $u ? $u->user_email : ''; ?>" required>
            <label>Password</label><input type="text" name="pw" placeholder="<?php echo $uid ? 'Vacío para no cambiar' : 'Contraseña para la App'; ?>" <?php echo $uid ? '' : 'required'; ?>>
            
            <label>Código F018...</label><input type="text" name="co" value="<?php echo $c ? $c->codigo_interno : ''; ?>">
            
            <label>Ramo CNT</label>
            <select name="ra">
                <option value="">Seleccionar...</option>
                <?php foreach($ramos_cnt as $r) echo "<option value='$r' ".($c && $c->ramo==$r?'selected':'').">$r</option>"; ?>
            </select>

            <label>Situación</label>
            <select name="si"><option value="Alta" <?php echo ($c && $c->situacion=='Alta')?'selected':''; ?>>Alta</option><option value="Baja" <?php echo ($c && $c->situacion=='Baja')?'selected':''; ?>>Baja</option></select>

            <label>Género / Teléfono</label>
            <div style="display:flex; gap:10px;">
                <select name="ge" style="width:40%"><option value="">Género...</option><option <?php echo ($c && $c->genero=='Hombre')?'selected':''; ?>>Hombre</option><option <?php echo ($c && $c->genero=='Mujer')?'selected':''; ?>>Mujer</option><option <?php echo ($c && $c->genero=='Otro')?'selected':''; ?>>Otro</option></select>
                <input type="text" name="te" value="<?php echo $c ? $c->telefono : ''; ?>" placeholder="Teléfono" style="width:60%">
            </div>

            <label>Fecha Nacimiento</label><input type="text" name="fn" value="<?php echo $c ? $c->fecha_nacimiento : ''; ?>" placeholder="DD/MM/AAAA">

            <label>IBAN (Cifrado)</label><input type="text" name="ib" value="<?php echo $c ? Montseny_Crypto::decrypt($c->iban_cifrado) : ''; ?>">
            <label>Dirección (Cifrada)</label><input type="text" name="di" value="<?php echo $c ? Montseny_Crypto::decrypt($c->direccion_cifrada) : ''; ?>">

            <label>Etiquetas</label>
            <input type="text" name="ta" list="tags-list" value="<?php echo $c ? $c->etiquetas : ''; ?>" placeholder="Empresa, aficiones, habilidades...">
            <datalist id="tags-list">
                <?php foreach($existing_tags as $et) echo "<option value='$et'>"; ?>
            </datalist>
            <p class="hint">Separadas por comas. Sugerirá etiquetas ya usadas.</p>

            <label>Observaciones Abiertas</label>
            <textarea name="ob" rows="4"><?php echo $c ? $c->observaciones : ''; ?></textarea>

            <button type="submit" name="m_save_afiliado" class="btn">GUARDAR COMPA</button>
            <a href="?" style="color:#888; display:block; text-align:center; margin-top:15px; text-decoration:none;">← Cancelar y Volver</a>
        </form>
    <?php else : ?>
        <div class="tabs">
            <a href="?ver=Alta" class="tab <?php echo ($filtro=='Alta')?'active':''; ?>">ACTIVOS (<?php echo ($filtro=='Alta')?count($afiliados):'...'; ?>)</a>
            <a href="?ver=Baja" class="tab <?php echo ($filtro=='Baja')?'active':''; ?>">BAJAS (<?php echo ($filtro=='Baja')?count($afiliados):'...'; ?>)</a>
        </div>
        <a href="?view=alta" class="btn">➕ NUEVO AFILIADO</a>
        
        <h3 style="margin-top:25px; color:#CC0000;">Lista de Afiliación</h3>
        <?php foreach($afiliados as $af): ?>
            <div class="item">
                <span><strong><?php echo $af->display_name; ?></strong><br><small style="color:#aaa;"><?php echo $af->codigo_interno; ?></small></span>
                <a href="?edit=<?php echo $af->ID; ?>" style="color:#CC0000; font-weight:bold; text-decoration:none; background:#333; padding:5px 10px; border-radius:4px;">EDITAR</a>
            </div>
        <?php endforeach; ?>
        <a href="<?php echo site_url('/montseny'); ?>" style="color:#666; display:block; text-align:center; margin-top:30px; font-size:0.8rem; text-decoration:none;">← SALIR A LA APP</a>
    <?php endif; ?>
    </body></html>
    <?php
}
