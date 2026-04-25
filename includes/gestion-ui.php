<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_render_gestion() {
    global $wpdb; $tabla = $wpdb->prefix . 'montseny_afiliados';

    // --- ACCIÓN: IMPORTAR CSV ---
    if (isset($_POST['m_import']) && !empty($_FILES['csv']['tmp_name'])) {
        $file = $_FILES['csv']['tmp_name']; $handle = fopen($file, "r");
        $first_line = fgets($handle); rewind($handle);
        $sep = (strpos($first_line, ';') !== false) ? ';' : ',';
        fgetcsv($handle, 0, $sep); // Saltar cabecera
        $ex = 0; $er = 0;
        while (($data = fgetcsv($handle, 0, $sep)) !== FALSE) {
            if (empty($data[0])) continue;
            $tmp_user = 'af_' . time() . rand(10,99);
            $uid = wp_create_user($tmp_user, wp_generate_password(12), $tmp_user . '@temporal.cnt');
            if (!is_wp_error($uid)) {
                wp_update_user(array('ID' => $uid, 'display_name' => $data[0].' '.$data[1]));
                $u = new WP_User($uid); $u->set_role('afiliade');
                $wpdb->insert($tabla, array(
                    'user_id' => $uid,
                    'genero' => $data[3],
                    'ramo' => $data[5],
                    'fecha_alta' => date('Y-m-d', strtotime(str_replace('/','-',$data[8]))),
                    'observaciones' => $data[10],
                    'etiquetas' => $data[4]
                ));
                $ex++;
            } else { $er++; }
        }
        fclose($handle); wp_redirect(site_url('/montseny/gestion/?msg=imported&ok='.$ex.'&err='.$er)); exit;
    }

    // --- ACCIÓN: ALTA MANUAL (TODOS LOS CAMPOS) ---
    if (isset($_POST['m_alta_manual'])) {
        $uid = wp_create_user(sanitize_email($_POST['em']), $_POST['pw'], sanitize_email($_POST['em']));
        if (!is_wp_error($uid)) {
            wp_update_user(array('ID' => $uid, 'display_name' => sanitize_text_field($_POST['no'])));
            $u = new WP_User($uid); $u->set_role('afiliade');
            $wpdb->insert($tabla, array(
                'user_id' => $uid,
                'genero' => $_POST['ge'],
                'fecha_nacimiento' => $_POST['fn'],
                'telefono' => $_POST['te'],
                'ramo' => $_POST['ra'],
                'etiquetas' => $_POST['ta'],
                'observaciones' => $_POST['ob'],
                'direccion_cifrada' => Montseny_Crypto::encrypt($_POST['di']),
                'iban_cifrado' => Montseny_Crypto::encrypt($_POST['ib']),
                'fecha_alta' => date('Y-m-d')
            ));
            wp_redirect(site_url('/montseny/gestion/?msg=manual_ok')); exit;
        } else { wp_redirect(site_url('/montseny/gestion/?msg=manual_err&detail='.urlencode($uid->get_error_message()))); exit; }
    }

    // --- ACCIÓN: GUARDAR EDICIÓN ---
    if (isset($_POST['save_edit'])) {
        $uid = intval($_POST['uid']);
        wp_update_user(array('ID'=>$uid, 'user_email'=>sanitize_email($_POST['em']), 'display_name'=>sanitize_text_field($_POST['no'])));
        if(!empty($_POST['pw'])) wp_set_password($_POST['pw'], $uid);
        $wpdb->update($tabla, array(
            'ramo'=>$_POST['ra'], 'etiquetas'=>$_POST['ta'], 'observaciones'=>$_POST['ob'],
            'genero'=>$_POST['ge'], 'fecha_nacimiento'=>$_POST['fn'], 'telefono'=>$_POST['te'],
            'direccion_cifrada'=>Montseny_Crypto::encrypt($_POST['di']), 'iban_cifrado'=>Montseny_Crypto::encrypt($_POST['ib'])
        ), array('user_id'=>$uid));
        wp_redirect(site_url('/montseny/gestion/?msg=updated')); exit;
    }

    $afiliados = $wpdb->get_results("SELECT * FROM $tabla ORDER BY id DESC");
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #111; color: #fff; margin: 0; }
        .bar { background: #333; padding: 15px; text-align: center; border-bottom: 2px solid #CC0000; font-weight:bold; }
        .container { padding: 15px; }
        .btn { background: #CC0000; color: #fff; padding: 12px; border-radius: 6px; text-decoration: none; border:none; font-weight:bold; cursor:pointer; display:inline-block; font-size:0.9rem; }
        .card-box { background: #222; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        input, textarea, select { width: 100%; padding: 12px; margin: 10px 0; background: #333; color: #fff; border: 1px solid #444; border-radius: 5px; box-sizing: border-box; }
        label { color: #CC0000; font-weight: bold; font-size: 0.8rem; }
        .item { background: #1a1a1a; padding: 12px; border-radius: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid #CC0000; }
        .msg-ok { background: #004400; padding: 10px; border-radius: 5px; margin-bottom: 15px; border-left: 5px solid #00ff00; font-weight: bold; }
    </style></head><body>
    <div class="bar">GESTIÓN MONTSENY</div>
    <div class="container">

        <?php if(isset($_GET['msg'])): ?><div class="msg-ok">✅ Operación realizada con éxito.</div><?php endif; ?>

        <div style="margin-bottom:20px; display:flex; gap:10px;">
            <a href="?view=alta" class="btn">➕ AÑADIR A MANO</a>
            <a href="?view=import" class="btn" style="background:#444;">📥 IMPORTAR CSV</a>
        </div>

        <?php if (isset($_GET['view']) && $_GET['view'] == 'alta') : ?>
            <div class="card-box">
                <h3>Nuevo Afiliado</h3>
                <form method="post">
                    <input type="text" name="no" placeholder="Nombre completo" required>
                    <input type="email" name="em" placeholder="Email (Login)" required>
                    <input type="text" name="pw" placeholder="Contraseña App" required>
                    <input type="text" name="ra" placeholder="Ramo">
                    <select name="ge"><option value="">Género</option><option value="Hombre">Hombre</option><option value="Mujer">Mujer</option><option value="Otro">Otro</option></select>
                    <input type="text" name="fn" placeholder="Fecha Nacimiento">
                    <input type="text" name="te" placeholder="Teléfono">
                    <input type="text" name="ib" placeholder="IBAN">
                    <input type="text" name="di" placeholder="Dirección">
                    <textarea name="ta" placeholder="Etiquetas (separadas por comas)"></textarea>
                    <textarea name="ob" placeholder="Observaciones"></textarea>
                    <button type="submit" name="m_alta_manual" class="btn" style="width:100%">CREAR AFILIADO</button>
                    <a href="?" style="display:block; text-align:center; margin-top:15px; color:#888;">Cancelar</a>
                </form>
            </div>
        <?php elseif (isset($_GET['edit'])) : 
            $uid = intval($_GET['edit']); $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $uid)); $u = get_userdata($uid);
        ?>
            <div class="card-box">
                <h3>Editar: <?php echo $u->display_name; ?></h3>
                <form method="post">
                    <input type="hidden" name="uid" value="<?php echo $uid; ?>">
                    <label>Nombre</label><input type="text" name="no" value="<?php echo $u->display_name; ?>">
                    <label>Email</label><input type="email" name="em" value="<?php echo (strpos($u->user_email, '@temporal.cnt')===false)?$u->user_email:''; ?>">
                    <label>Nueva Password</label><input type="text" name="pw" placeholder="Dejar en blanco para no cambiar">
                    <label>IBAN</label><input type="text" name="ib" value="<?php echo Montseny_Crypto::decrypt($c->iban_cifrado); ?>">
                    <label>Dirección</label><input type="text" name="di" value="<?php echo Montseny_Crypto::decrypt($c->direccion_cifrada); ?>">
                    <label>Teléfono</label><input type="text" name="te" value="<?php echo $c->telefono; ?>">
                    <label>Ramo</label><input type="text" name="ra" value="<?php echo $c->ramo; ?>">
                    <label>Etiquetas</label><input type="text" name="ta" value="<?php echo $c->etiquetas; ?>">
                    <label>Observaciones</label><textarea name="ob"><?php echo $c->observaciones; ?></textarea>
                    <button type="submit" name="save_edit" class="btn" style="width:100%">GUARDAR CAMBIOS</button>
                    <a href="?" style="display:block; text-align:center; margin-top:15px; color:#888;">Volver</a>
                </form>
            </div>
        <?php else : ?>
            <h3>Afiliación (<?php echo count($afiliados); ?>)</h3>
            <?php foreach($afiliados as $af): $u = get_userdata($af->user_id); ?>
                <div class="item">
                    <span><?php echo $u->display_name; ?></span>
                    <a href="?edit=<?php echo $af->user_id; ?>" class="btn" style="padding:5px 10px; font-size:0.7rem; background:#333;">EDITAR</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <br><a href="<?php echo site_url('/montseny'); ?>" style="color:#666;">← Salir a la App</a>
    </div></body></html>
    <?php
}
