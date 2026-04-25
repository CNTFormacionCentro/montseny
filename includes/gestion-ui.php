<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LÓGICA DE ACCIONES (IMPORTAR / ALTA MANUAL / GUARDAR)
 */
add_action('init', function() {
    if ( strpos($_SERVER['REQUEST_URI'], '/montseny/gestion') === false ) return;
    if ( !current_user_can('montseny_tesorero') && !current_user_can('manage_options') ) return;

    global $wpdb; $tabla = $wpdb->prefix . 'montseny_afiliados';

    // --- 1. ACCIÓN: IMPORTAR CSV ---
    if (isset($_POST['m_import']) && !empty($_FILES['csv']['tmp_name'])) {
        $file = $_FILES['csv']['tmp_name'];
        $handle = fopen($file, "r");
        $first_line = fgets($handle); rewind($handle);
        $sep = (strpos($first_line, ';') !== false) ? ';' : ',';
        fgetcsv($handle, 0, $sep); // Saltar cabecera
        
        $exitos = 0; $errores = 0;
        while (($data = fgetcsv($handle, 0, $sep)) !== FALSE) {
            if (empty($data[0])) continue;
            $nombre = $data[0] . ' ' . (isset($data[1]) ? $data[1] : '');
            $tmp_user = 'af_' . time() . rand(10,99);
            $uid = wp_create_user($tmp_user, wp_generate_password(12), $tmp_user . '@temporal.cnt');
            if ( !is_wp_error($uid) ) {
                wp_update_user(array('ID' => $uid, 'display_name' => $nombre));
                $u = new WP_User($uid); $u->set_role('afiliade');
                $wpdb->insert($tabla, array(
                    'user_id' => $uid,
                    'ramo' => isset($data[5]) ? $data[5] : '',
                    'observaciones' => isset($data[10]) ? $data[10] : '',
                    'fecha_alta' => (isset($data[8]) && !empty($data[8])) ? date('Y-m-d', strtotime(str_replace('/','-',$data[8]))) : date('Y-m-d')
                ));
                $exitos++;
            } else { $errores++; }
        }
        fclose($handle);
        wp_redirect(site_url('/montseny/gestion/?msg=imported&ok='.$exitos.'&err='.$errores)); exit;
    }

    // --- 2. ACCIÓN: ALTA MANUAL DESDE LA APP ---
    if (isset($_POST['m_alta_manual'])) {
        $email = sanitize_email($_POST['em']);
        $uid = wp_create_user($email, $_POST['pw'], $email);
        if ( !is_wp_error($uid) ) {
            wp_update_user(array('ID' => $uid, 'display_name' => sanitize_text_field($_POST['no'])));
            $u = new WP_User($uid); $u->set_role('afiliade');
            $wpdb->insert($tabla, array(
                'user_id' => $uid,
                'ramo' => sanitize_text_field($_POST['ra']),
                'observaciones' => sanitize_textarea_field($_POST['ob']),
                'fecha_alta' => date('Y-m-d')
            ));
            wp_redirect(site_url('/montseny/gestion/?msg=manual_ok')); exit;
        } else {
            wp_redirect(site_url('/montseny/gestion/?msg=manual_err&detail='.urlencode($uid->get_error_message()))); exit;
        }
    }

    // --- 3. ACCIÓN: GUARDAR EDICIÓN ---
    if (isset($_POST['save_edit'])) {
        $uid = intval($_POST['uid']);
        wp_update_user(array('ID'=>$uid, 'user_email'=>sanitize_email($_POST['em']), 'display_name'=>sanitize_text_field($_POST['no'])));
        if(!empty($_POST['pw'])) wp_set_password($_POST['pw'], $uid);
        $wpdb->update($tabla, array(
            'ramo'=>$_POST['ra'], 'etiquetas'=>$_POST['ta'], 'observaciones'=>$_POST['ob'],
            'direccion_cifrada'=>Montseny_Crypto::encrypt($_POST['di']), 'iban_cifrado'=>Montseny_Crypto::encrypt($_POST['ib'])
        ), array('user_id'=>$uid));
        wp_redirect(site_url('/montseny/gestion/?msg=updated')); exit;
    }
});

/**
 * INTERFAZ VISUAL DE GESTIÓN
 */
function montseny_render_gestion() {
    global $wpdb; $tabla = $wpdb->prefix . 'montseny_afiliados';
    $afiliados = $wpdb->get_results("SELECT * FROM $tabla ORDER BY id DESC");
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #111; color: #fff; margin: 0; }
        .bar { background: #333; padding: 15px; text-align: center; border-bottom: 2px solid #CC0000; font-weight:bold; }
        .container { padding: 15px; }
        .btn { background: #CC0000; color: #fff; padding: 12px; border-radius: 6px; text-decoration: none; border:none; font-weight:bold; cursor:pointer; display:inline-block; font-size:0.9rem; }
        .btn-grey { background: #444; }
        .card-box { background: #222; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #333; }
        input, textarea, select { width: 100%; padding: 12px; margin: 10px 0; background: #333; color: #fff; border: 1px solid #444; border-radius: 5px; box-sizing: border-box; }
        .msg { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.9rem; font-weight: bold; }
        .msg-ok { background: #004400; border-left: 5px solid #00ff00; }
        .msg-err { background: #440000; border-left: 5px solid #ff0000; }
        .item { background: #1a1a1a; padding: 12px; border-radius: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid #CC0000; }
    </style></head><body>
    <div class="bar">GESTIÓN SINDICAL MONTSENY</div>
    <div class="container">

        <!-- MENSAJES DE FEEDBACK -->
        <?php if(isset($_GET['msg'])): ?>
            <?php if($_GET['msg']=='imported'): ?><div class="msg msg-ok">✅ CSV: <?php echo intval($_GET['ok']); ?> importados / <?php echo intval($_GET['err']); ?> fallos.</div><?php endif; ?>
            <?php if($_GET['msg']=='manual_ok'): ?><div class="msg msg-ok">✅ Compañere creado correctamente.</div><?php endif; ?>
            <?php if($_GET['msg']=='manual_err'): ?><div class="msg msg-err">❌ Error: <?php echo esc_html($_GET['detail']); ?></div><?php endif; ?>
            <?php if($_GET['msg']=='updated'): ?><div class="msg msg-ok">✅ Datos actualizados.</div><?php endif; ?>
        <?php endif; ?>

        <!-- BOTONES DE ACCIÓN RÁPIDA -->
        <div style="margin-bottom:20px; display:flex; gap:10px;">
            <a href="?view=alta" class="btn">➕ AÑADIR A MANO</a>
            <a href="?view=import" class="btn btn-grey">📥 IMPORTAR CSV</a>
        </div>

        <?php if (isset($_GET['view']) && $_GET['view'] == 'alta') : ?>
            <!-- FORMULARIO ALTA MANUAL -->
            <div class="card-box">
                <h3>Nuevo Afiliado</h3>
                <form method="post">
                    <input type="text" name="no" placeholder="Nombre y Apellidos" required>
                    <input type="email" name="em" placeholder="Email (Login App)" required>
                    <input type="text" name="pw" placeholder="Contraseña Provisional" required>
                    <input type="text" name="ra" placeholder="Ramo">
                    <textarea name="ob" placeholder="Observaciones"></textarea>
                    <button type="submit" name="m_alta_manual" class="btn" style="width:100%">CREAR AFILIADO</button>
                    <a href="?" style="display:block; text-align:center; margin-top:15px; color:#888; text-decoration:none;">Cancelar</a>
                </form>
            </div>

        <?php elseif (isset($_GET['view']) && $_GET['view'] == 'import') : ?>
            <!-- FORMULARIO IMPORTAR -->
            <div class="card-box">
                <h3>Importar desde Excel</h3>
                <p style="font-size:0.8rem; color:#aaa;">Recuerda: El archivo debe ser CSV (separado por comas).</p>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="csv" accept=".csv" required>
                    <button type="submit" name="m_import" class="btn" style="width:100%">SUBIR E IMPORTAR</button>
                    <a href="?" style="display:block; text-align:center; margin-top:15px; color:#888; text-decoration:none;">Cancelar</a>
                </form>
            </div>

        <?php elseif (isset($_GET['edit'])) : 
            $uid = intval($_GET['edit']); $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $uid)); $u = get_userdata($uid);
        ?>
            <!-- FORMULARIO EDICIÓN -->
            <div class="card-box">
                <h3>Editar: <?php echo $u->display_name; ?></h3>
                <form method="post">
                    <input type="hidden" name="uid" value="<?php echo $uid; ?>">
                    <label>Nombre</label><input type="text" name="no" value="<?php echo $u->display_name; ?>">
                    <label>Email</label><input type="email" name="em" value="<?php echo (strpos($u->user_email, '@temporal.cnt')===false)?$u->user_email:''; ?>">
                    <label>Nueva Contraseña</label><input type="text" name="pw" placeholder="Solo si quieres cambiarla">
                    <label>IBAN (Cifrado)</label><input type="text" name="ib" value="<?php echo Montseny_Crypto::decrypt($c->iban_cifrado); ?>">
                    <label>Dirección</label><input type="text" name="di" value="<?php echo Montseny_Crypto::decrypt($c->direccion_cifrada); ?>">
                    <label>Ramo</label><input type="text" name="ra" value="<?php echo $c->ramo; ?>">
                    <label>Etiquetas</label><input type="text" name="ta" value="<?php echo $c->etiquetas; ?>">
                    <label>Observaciones</label><textarea name="ob"><?php echo $c->observaciones; ?></textarea>
                    <button type="submit" name="save_edit" class="btn" style="width:100%">GUARDAR CAMBIOS</button>
                    <a href="?" style="display:block; text-align:center; margin-top:15px; color:#888; text-decoration:none;">Volver</a>
                </form>
            </div>

        <?php else : ?>
            <!-- LISTADO PRINCIPAL -->
            <h3>Afiliación (<?php echo count($afiliados); ?>)</h3>
            <?php foreach($afiliados as $af): $u = get_userdata($af->user_id); ?>
                <div class="item">
                    <span><?php echo $u->display_name; ?> <?php echo (strpos($u->user_email, '@temporal.cnt')!==false)?'⚠️':''; ?></span>
                    <a href="?edit=<?php echo $af->user_id; ?>" class="btn" style="padding:5px 10px; font-size:0.7rem; background:#333;">EDITAR</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <br><a href="<?php echo site_url('/montseny'); ?>" style="color:#666; font-size:0.8rem;">← Salir a la App</a>
    </div></body></html>
    <?php
}
