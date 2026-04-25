<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('init', function() {
    if (strpos($_SERVER['REQUEST_URI'], '/montseny') !== false && strpos($_SERVER['REQUEST_URI'], '/montseny/gestion') === false) {
        // Manejo de Login
        if (isset($_POST['m_login'])) {
            $u = wp_signon(array('user_login'=>$_POST['log'],'user_password'=>$_POST['pwd'],'remember'=>true), false);
            wp_redirect(site_url(is_wp_error($u) ? '/montseny/?err=1' : '/montseny/')); exit;
        }
        montseny_render_app(); exit;
    }
});

function montseny_render_app() {
    $local = get_option('montseny_nombre_local', 'CNT');
    $feeds = montseny_get_feeds();
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Montseny</title>
    <style>
        body { font-family: sans-serif; background: #000; color: #fff; margin: 0; padding-bottom: 50px; }
        .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; position: sticky; top: 0; z-index: 999; }
        .container { padding: 15px; }
        .card { background: #1a1a1a; border-left: 4px solid #CC0000; padding: 15px; margin-bottom: 12px; border-radius: 4px; }
        .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; border:none; width:100%; cursor:pointer; margin-top:10px; }
        .btn-choice { background: #333; margin-top: 20px; border: 1px solid #CC0000; }
        h3 { border-bottom: 1px solid #333; padding-bottom: 10px; margin-top: 30px; color: #CC0000; }
    </style></head><body>
    <div class="bar">MONTSENY - <?php echo $local; ?></div>
    <div class="container">
        <?php if ( is_user_logged_in() ) : 
            $u = wp_get_current_user();
            $is_staff = current_user_can('montseny_tesorero') || current_user_can('manage_options');
        ?>
            <!-- BIFURCACIÓN DE ACCESO -->
            <?php if ($is_staff && !isset($_GET['view_as_member'])) : ?>
                <div style="text-align:center; padding: 40px 0;">
                    <h2 style="margin-bottom:10px;">Salud, <?php echo $u->display_name; ?></h2>
                    <p style="color:#888;">Has accedido como responsable.</p>
                    <a href="?view_as_member=1" class="btn">VER MI CARNET</a>
                    <a href="<?php echo site_url('/montseny/gestion'); ?>" class="btn btn-choice">⚙️ PANEL DE GESTIÓN</a>
                    <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" style="color:#444; display:block; margin-top:40px; text-decoration:none;">Cerrar Sesión</a>
                </div>
            <?php else : ?>
                
                <!-- EL CARNET RÉPLICA -->
                <?php montseny_dibujar_carnet($u->ID); ?>

                <h3>Actualidad Sindical</h3>
                <?php if(empty($feeds)): ?> <p>No hay noticias recientes.</p> <?php endif; ?>
                <?php foreach($feeds as $n): ?>
                    <div class="card">
                        <small style="color:#CC0000"><?php echo $n['f']; ?></small>
                        <a href="<?php echo $n['l']; ?>" target="_blank" style="color:#fff; text-decoration:none;"><h4><?php echo $n['t']; ?></h4></a>
                    </div>
                <?php endforeach; ?>
                
                <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" style="color:#444; display:block; text-align:center; margin-top:40px; text-decoration:none; font-size:0.9rem;">Cerrar Sesión</a>
            <?php endif; ?>

        <?php else : ?>
            <form method="post" style="padding-top:30px;">
                <h3 style="text-align:center; color:#fff; border:none;">Acceso Afiliados</h3>
                <?php if(isset($_GET['err'])): ?> <p style="color:red; text-align:center;">Datos incorrectos</p> <?php endif; ?>
                <input type="text" name="log" placeholder="Email o Usuario" style="width:100%; padding:15px; margin-bottom:15px; border-radius:8px; border:none; background:#222; color:#fff; box-sizing:border-box;">
                <input type="password" name="pwd" placeholder="Contraseña" style="width:100%; padding:15px; margin-bottom:15px; border-radius:8px; border:none; background:#222; color:#fff; box-sizing:border-box;">
                <input type="hidden" name="m_login" value="1">
                <button type="submit" class="btn">IDENTIFICARSE</button>
            </form>
        <?php endif; ?>
    </div></body></html>
    <?php
}
