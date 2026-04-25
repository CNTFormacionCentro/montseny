<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_render_gestion() {
    global $wpdb; 
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $ramos_cnt = ["Oficios Varios", "Metal, Minería y Química", "Construcción y Madera", "Transportes y Comunicaciones", "Comercio, Hostelería y Alimentación", "Sanidad, Acción Social, Enseñanza y Cultura", "Administración y Servicios Públicos", "Artes Gráficas, Papel y Espectáculos", "Limpieza, Mantenimiento y Servicios Auxiliares", "Agroalimentario"];

    // --- ACCIÓN: GUARDAR NUEVO ---
    if (isset($_POST['m_alta_manual'])) {
        $email = sanitize_email($_POST['em']);
        $uid = wp_create_user($email, $_POST['pw'], $email);
        
        if (is_wp_error($uid)) {
            $error_msg = "Error en WordPress: " . $uid->get_error_message();
        } else {
            wp_update_user(['ID' => $uid, 'display_name' => sanitize_text_field($_POST['no'])]);
            (new WP_User($uid))->set_role('afiliade');
            
            $check = $wpdb->insert($tabla, [
                'user_id' => $uid,
                'situacion' => 'Alta',
                'codigo_interno' => sanitize_text_field($_POST['co']),
                'ramo' => $_POST['ra'],
                'genero' => $_POST['ge'],
                'telefono' => sanitize_text_field($_POST['te']),
                'direccion_cifrada' => Montseny_Crypto::encrypt($_POST['di']),
                'iban_cifrado' => Montseny_Crypto::encrypt($_POST['ib']),
                'etiquetas' => sanitize_text_field($_POST['ta']),
                'observaciones' => sanitize_textarea_field($_POST['ob']),
                'fecha_alta' => date('Y-m-d')
            ]);

            if ($check === false) {
                // SI ESTO FALLA, EL ERROR ES DE LA TABLA
                $error_msg = "Error de Base de Datos: " . $wpdb->last_error;
                wp_delete_user($uid); // Borramos el user de WP para no dejar fantasmas
            } else {
                wp_redirect(site_url('/montseny/gestion/?msg=ok')); exit;
            }
        }
    }

    // --- ACCIÓN: GUARDAR EDICIÓN ---
    if (isset($_POST['save_edit'])) {
        $uid = intval($_POST['uid']);
        wp_update_user(['ID' => $uid, 'user_email' => sanitize_email($_POST['em']), 'display_name' => sanitize_text_field($_POST['no'])]);
        if(!empty($_POST['pw'])) wp_set_password($_POST['pw'], $uid);

        $wpdb->update($tabla, [
            'codigo_interno' => sanitize_text_field($_POST['co']),
            'ramo' => $_POST['ra'],
            'genero' => $_POST['ge'],
            'telefono' => sanitize_text_field($_POST['te']),
            'etiquetas' => sanitize_text_field($_POST['ta']),
            'observaciones' => sanitize_textarea_field($_POST['ob']),
            'direccion_cifrada' => Montseny_Crypto::encrypt($_POST['di']),
            'iban_cifrado' => Montseny_Crypto::encrypt($_POST['ib'])
        ], ['user_id' => $uid]);
        wp_redirect(site_url('/montseny/gestion/?msg=ok')); exit;
    }

    // --- LÓGICA DE LISTADO ---
    $filtro = (isset($_GET['ver']) && $_GET['ver'] == 'Baja') ? 'Baja' : 'Alta';
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
        .item { background: #222; padding: 12px; border-radius: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid #CC0000; }
        .tabs { display: flex; gap: 5px; margin-bottom: 20px; }
        .tab { flex: 1; padding: 12px; text-align: center; background: #222; text-decoration: none; color: #888; border-radius: 6px; font-weight:bold; border: 1px solid #333; }
        .tab.active { background: #CC0000; color: #fff; }
        input, select, textarea { width: 100%; padding: 12px; margin: 8px 0; background: #333; color: #fff; border: 1px solid #444; border-radius: 5px; box-sizing: border-box; }
        .msg-err { background: #600; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #f00; }
    </style></head><body>
    <div class="bar">GESTIÓN MONTSENY</div>
    <div class="container">

        <?php if(isset($error_msg)): ?>
            <div class="msg-err"><strong>❌ ERROR DE GRABACIÓN:</strong><br><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="tabs">
            <a href="?ver=Alta" class="tab <?php echo ($filtro=='Alta')?'active':''; ?>">ACTIVOS</a>
            <a href="?ver=Baja" class="tab <?php echo ($filtro=='Baja')?'active':''; ?>">BAJAS</a>
        </div>

        <?php if (isset($_GET['view']) && $_GET['view'] == 'alta') : ?>
            <h3>Nuevo Afiliado</h3>
            <form method="post">
                <input type="text" name="no" placeholder="Nombre completo" required>
                <input type="email" name="em" placeholder="Email" required>
                <input type="text" name="pw" placeholder="Contraseña App" required>
                <input type="text" name="co" placeholder="Código (F018...)">
                <select name="ra"><option value="">Ramo...</option><?php foreach($ramos_cnt as $r) echo "<option value='$r'>$r</option>"; ?></select>
                <input type="text" name="ib" placeholder="IBAN">
                <input type="text" name="di" placeholder="Dirección">
                <button type="submit" name="m_alta_manual" class="btn">GRABAR AHORA</button>
                <a href="?" class="btn" style="background:#444; margin-top:10px;">CANCELAR</a>
            </form>
        <?php elseif (isset($_GET['edit'])) : 
            $uid = intval($_GET['edit']); $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $uid)); $u = get_userdata($uid);
        ?>
            <h3>Editar: <?php echo $u->display_name; ?></h3>
            <form method="post">
                <input type="hidden" name="uid" value="<?php echo $uid; ?>">
                <input type="text" name="no" value="<?php echo $u->display_name; ?>">
                <input type="email" name="em" value="<?php echo $u->user_email; ?>">
                <input type="text" name="pw" placeholder="Cambiar clave">
                <input type="text" name="co" value="<?php echo $c->codigo_interno; ?>">
                <select name="ra"><?php foreach($ramos_cnt as $r) echo "<option value='$r' ".($c->ramo==$r?'selected':'').">$r</option>"; ?></select>
                <input type="text" name="ib" value="<?php echo Montseny_Crypto::decrypt($c->iban_cifrado); ?>">
                <input type="text" name="di" value="<?php echo Montseny_Crypto::decrypt($c->direccion_cifrada); ?>">
                <button type="submit" name="save_edit" class="btn">GUARDAR</button>
            </form>
        <?php else : ?>
            <a href="?view=alta" class="btn" style="margin-bottom:20px;">➕ AÑADIR A MANO</a>
            <h3>Afiliación (<?php echo count($results); ?>)</h3>
            <?php foreach($results as $af): ?>
                <div class="item">
                    <span><strong><?php echo $af->display_name; ?></strong><br><small><?php echo $af['codigo']; ?></small></span>
                    <a href="?edit=<?php echo $af->ID; ?>" class="btn" style="width:auto; padding:5px 15px; font-size:0.8rem; background:#444;">EDITAR</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div></body></html>
    <?php
}
