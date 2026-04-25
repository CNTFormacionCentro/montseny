<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_render_gestion() {
    global $wpdb; $tabla = $wpdb->prefix . 'montseny_afiliados';
    $ramos_cnt = [
        "Oficios Varios", "Metal, Minería y Química", "Construcción y Madera", 
        "Transportes y Comunicaciones", "Comercio, Hostelería y Alimentación", 
        "Sanidad, Acción Social, Enseñanza y Cultura", "Administración y Servicios Públicos", 
        "Artes Gráficas, Papel y Espectáculos", "Limpieza, Mantenimiento y Servicios Auxiliares", 
        "Agroalimentario"
    ];

    // --- ACCIÓN: IMPORTAR ---
    if (isset($_POST['m_import']) && !empty($_FILES['csv']['tmp_name'])) {
        $f = fopen($_FILES['csv']['tmp_name'], 'r'); $sep = (strpos(fgets($f), ';') !== false) ? ';' : ','; rewind($f);
        fgetcsv($f, 0, $sep); $ok = 0;
        while (($r = fgetcsv($f, 0, $sep)) !== FALSE) {
            if (empty($r[0])) continue;
            $tmp_u = 'af_' . time() . rand(10,99);
            $uid = wp_create_user($tmp_u, wp_generate_password(12), $tmp_u . '@temporal.cnt');
            if (!is_wp_error($uid)) {
                wp_update_user(['ID'=>$uid, 'display_name'=>$r[0].' '.$r[1]]);
                (new WP_User($uid))->set_role('afiliade');
                $wpdb->insert($tabla, [
                    'user_id'=>$uid, 'genero'=>$r[3], 'ramo'=>$r[5], 'fecha_alta'=>date('Y-m-d', strtotime(str_replace('/','-',$r[8]))), 
                    'observaciones'=>$r[10], 'etiquetas'=>$r[4], 'codigo_interno'=>'IMP-'.rand(100,999)
                ]);
                $ok++;
            }
        }
        fclose($f); wp_redirect(site_url('/montseny/gestion/?msg=ok&count='.$ok)); exit;
    }

    // --- ACCIÓN: ALTA MANUAL ---
    if (isset($_POST['m_alta_manual'])) {
        $uid = wp_create_user(sanitize_email($_POST['em']), $_POST['pw'], sanitize_email($_POST['em']));
        if (!is_wp_error($uid)) {
            wp_update_user(['ID' => $uid, 'display_name' => sanitize_text_field($_POST['no'])]);
            (new WP_User($uid))->set_role('afiliade');
            $wpdb->insert($tabla, [
                'user_id' => $uid, 'codigo_interno' => sanitize_text_field($_POST['co']),
                'genero' => $_POST['ge'], 'fecha_nacimiento' => $_POST['fn'], 'telefono' => $_POST['te'],
                'ramo' => $_POST['ra'], 'etiquetas' => $_POST['ta'], 'observaciones' => $_POST['ob'],
                'direccion_cifrada' => Montseny_Crypto::encrypt($_POST['di']),
                'iban_cifrado' => Montseny_Crypto::encrypt($_POST['ib']), 'fecha_alta' => date('Y-m-d')
            ]);
            wp_redirect(site_url('/montseny/gestion/?msg=ok')); exit;
        } else { $err = $uid->get_error_message(); }
    }

    $afiliados = $wpdb->get_results("SELECT * FROM $tabla ORDER BY id DESC");
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #111; color: #fff; margin: 0; }
        .bar { background: #333; padding: 15px; text-align: center; border-bottom: 2px solid #CC0000; font-weight:bold; }
        .container { padding: 15px; }
        .btn { background: #CC0000; color: #fff; padding: 12px; border-radius: 6px; text-decoration: none; border:none; font-weight:bold; cursor:pointer; display:inline-block; width:100%; box-sizing:border-box; text-align:center;}
        .btn-grey { background: #444; margin-top:10px; }
        .item { background: #222; padding: 12px; border-radius: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid #CC0000; }
        input, select, textarea { width: 100%; padding: 12px; margin: 8px 0; background: #333; color: #fff; border: 1px solid #444; border-radius: 5px; box-sizing: border-box; }
        .msg { background: #004400; padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center; font-weight: bold; }
    </style></head><body>
    <div class="bar">GESTIÓN MONTSENY</div>
    <div class="container">
        <?php if(isset($_GET['msg'])) echo '<div class="msg">✅ Operación completada</div>'; ?>
        <?php if(isset($err)) echo '<div class="msg" style="background:#600">❌ '.$err.'</div>'; ?>

        <div style="margin-bottom:20px;">
            <a href="?view=alta" class="btn">➕ NUEVO AFILIADO</a>
            <a href="?view=import" class="btn btn-grey">📥 IMPORTAR EXCEL (CSV)</a>
        </div>

        <?php if (isset($_GET['view']) && $_GET['view'] == 'alta') : ?>
            <form method="post">
                <h3>Datos Personales</h3>
                <input type="text" name="no" placeholder="Nombre completo" required>
                <input type="email" name="em" placeholder="Email (Usuario)" required>
                <input type="text" name="pw" placeholder="Contraseña Provisional" required>
                <input type="text" name="te" placeholder="Teléfono">
                <select name="ge"><option value="">Género</option><option>Hombre</option><option>Mujer</option><option>Otro</option></select>
                <input type="text" name="fn" placeholder="Fecha Nacimiento">
                <h3>Datos Sindicales</h3>
                <input type="text" name="co" placeholder="Código Carnet (F018...)" required>
                <select name="ra"><?php foreach($ramos_cnt as $r) echo "<option value='$r'>$r</option>"; ?></select>
                <input type="text" name="ib" placeholder="IBAN (Cifrado)">
                <input type="text" name="di" placeholder="Dirección (Cifrada)">
                <textarea name="ta" placeholder="Etiquetas (separadas por comas)"></textarea>
                <textarea name="ob" placeholder="Observaciones"></textarea>
                <button type="submit" name="m_alta_manual" class="btn">GRABAR AFILIADO</button>
                <a href="?" class="btn btn-grey">CANCELAR</a>
            </form>
        <?php elseif (isset($_GET['view']) && $_GET['view'] == 'import') : ?>
            <form method="post" enctype="multipart/form-data">
                <p>Archivo CSV (A-K, separado por comas o punto y coma)</p>
                <input type="file" name="csv" accept=".csv" required>
                <button type="submit" name="m_import" class="btn">SUBIR E IMPORTAR</button>
                <a href="?" class="btn btn-grey">CANCELAR</a>
            </form>
        <?php else : ?>
            <h3>Lista de Afiliación (<?php echo count($afiliados); ?>)</h3>
            <?php if(empty($afiliados)) echo "<p>No hay datos. Prueba a añadir a alguien a mano.</p>"; ?>
            <?php foreach($afiliados as $af): $u = get_userdata($af->user_id); if($u): ?>
                <div class="item">
                    <span><strong><?php echo $u->display_name; ?></strong><br><small><?php echo $af->codigo_interno; ?></small></span>
                    <a href="?edit=<?php echo $af->user_id; ?>" style="color:#CC0000; text-decoration:none; font-weight:bold;">EDITAR</a>
                </div>
            <?php endif; endforeach; ?>
        <?php endif; ?>
        <br><a href="<?php echo site_url('/montseny'); ?>" style="color:#666; display:block; text-align:center;">← Volver a la App</a>
    </div></body></html>
    <?php
}
