<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('init', function() {
    if ( strpos($_SERVER['REQUEST_URI'], '/montseny/gestion') !== false ) {
        if (!current_user_can('montseny_tesorero') && !current_user_can('manage_options')) {
            wp_redirect(site_url('/montseny/')); exit;
        }
        montseny_render_gestion(); exit;
    }
});

function montseny_render_gestion() {
    global $wpdb; $tabla = $wpdb->prefix . 'montseny_afiliados';

    // 1. LÓGICA: IMPORTADOR CSV A-K
    if (isset($_POST['m_import']) && !empty($_FILES['csv']['tmp_name'])) {
        $f = fopen($_FILES['csv']['tmp_name'], 'r'); fgetcsv($f, 0, ";"); // Saltar cabecera
        while (($r = fgetcsv($f, 0, ";")) !== FALSE) {
            $nom = $r[0].' '.$r[1]; $tmp_u = 'tmp_'.time().rand(10,99);
            $uid = wp_create_user($tmp_u, wp_generate_password(12), $tmp_u.'@temporal.cnt');
            if(!is_wp_error($uid)) {
                wp_update_user(array('ID'=>$uid, 'display_name'=>$nom));
                $u = new WP_User($uid); $u->set_role('afiliade');
                $wpdb->insert($tabla, array('user_id'=>$uid, 'genero'=>$r[3], 'ramo'=>$r[5], 'fecha_alta'=>$r[8], 'observaciones'=>$r[10], 'etiquetas'=>$r[4]));
            }
        }
        fclose($f); wp_redirect(site_url('/montseny/gestion/?msg=imported')); exit;
    }

    // 2. LÓGICA: GUARDAR EDICIÓN
    if (isset($_POST['save_afiliado'])) {
        $uid = intval($_POST['uid']);
        wp_update_user(array('ID'=>$uid, 'user_email'=>sanitize_email($_POST['em']), 'display_name'=>sanitize_text_field($_POST['no'])));
        if(!empty($_POST['pw'])) wp_set_password($_POST['pw'], $uid);
        $wpdb->update($tabla, array(
            'ramo'=>$_POST['ra'], 'etiquetas'=>$_POST['ta'], 'observaciones'=>$_POST['ob'],
            'direccion_cifrada'=>Montseny_Crypto::encrypt($_POST['di']), 'iban_cifrado'=>Montseny_Crypto::encrypt($_POST['ib'])
        ), array('user_id'=>$uid));
        wp_redirect(site_url('/montseny/gestion/?msg=updated')); exit;
    }

    $afiliados = $wpdb->get_results("SELECT * FROM $tabla");
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #111; color: #fff; margin: 0; }
        .bar { background: #333; padding: 15px; text-align: center; border-bottom: 2px solid #CC0000; font-weight:bold; }
        .container { padding: 15px; }
        .aviso { background: #442200; padding: 15px; border-radius: 5px; font-size: 0.85rem; margin-bottom: 20px; border-left: 4px solid #ff9900; line-height:1.4; }
        .item { background: #222; padding: 15px; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #444; }
        .btn-r { background: #CC0000; color: #fff; padding: 10px; border-radius: 5px; text-decoration: none; border:none; width:100%; display:block; text-align:center; font-weight:bold; cursor:pointer; }
        input, textarea { width: 100%; padding: 12px; margin-bottom: 15px; background: #333; border: 1px solid #555; color: #fff; border-radius: 5px; box-sizing: border-box; }
        label { color: #CC0000; font-weight: bold; font-size: 0.8rem; display: block; margin-bottom: 5px; }
    </style></head><body>
    <div class="bar">GESTIÓN MONTSENY</div>
    <div class="container">
        <a href="<?php echo site_url('/montseny'); ?>" style="color:#aaa; text-decoration:none; font-size:0.8rem;">← Volver a la App</a>
        
        <?php if (isset($_GET['edit'])) : 
            $uid = intval($_GET['edit']); $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $uid)); $u = get_userdata($uid);
        ?>
            <!-- FORMULARIO EDICIÓN -->
            <h3 style="margin-top:20px;">Editar: <?php echo $u->display_name; ?></h3>
            <form method="post">
                <input type="hidden" name="uid" value="<?php echo $uid; ?>">
                <label>Nombre Completo</label><input type="text" name="no" value="<?php echo $u->display_name; ?>">
                <label>Email (Login)</label><input type="email" name="em" value="<?php echo (strpos($u->user_email, '@temporal.cnt')===false)?$u->user_email:''; ?>" placeholder="obligatorio para usar la App">
                <label>Nueva Contraseña</label><input type="text" name="pw" placeholder="Dejar en blanco para no cambiar">
                <label>IBAN (Cifrado)</label><input type="text" name="ib" value="<?php echo Montseny_Crypto::decrypt($c->iban_cifrado); ?>">
                <label>Dirección Postal (Cifrada)</label><input type="text" name="di" value="<?php echo Montseny_Crypto::decrypt($c->direccion_cifrada); ?>">
                <label>Ramo</label><input type="text" name="ra" value="<?php echo $c->ramo; ?>">
                <label>Etiquetas</label><input type="text" name="ta" value="<?php echo $c->etiquetas; ?>">
                <label>Observaciones</label><textarea name="ob" rows="3"><?php echo $c->observaciones; ?></textarea>
                <button type="submit" name="save_afiliado" class="btn-r">GUARDAR CAMBIOS</button>
            </form>
        <?php else : ?>
            <!-- VISTA PRINCIPAL GESTIÓN -->
            <div class="aviso">
                <strong>¡Atención, Tesorería!</strong> Por limitaciones técnicas, este sistema solo lee archivos <strong>CSV</strong>. <br><br>
                Antes de subir tu Excel, dale a "Guardar como" y elige <strong>CSV (delimitado por comas)</strong>. El campo Edad se ignorará automáticamente.
            </div>
            <form method="post" enctype="multipart/form-data" style="margin-bottom:30px; padding:15px; border:1px dashed #555;">
                <input type="file" name="csv" accept=".csv" required style="margin-bottom:10px;"><br>
                <button type="submit" name="m_import" class="btn-r">SUBIR E IMPORTAR</button>
            </form>
            <h3>Lista de Afiliación</h3>
            <?php foreach($afiliados as $af): $u = get_userdata($af->user_id); ?>
                <div class="item">
                    <span><?php echo $u->display_name; ?> <?php echo (strpos($u->user_email, '@temporal.cnt')!==false)?'⚠️':''; ?></span>
                    <a href="?edit=<?php echo $af->user_id; ?>" class="btn-r" style="width:auto; padding:5px 10px;">EDITAR</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div></body></html>
    <?php
}
