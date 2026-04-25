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
    <style>
        body { font-family: sans-serif; background: #000; color: #fff; margin: 0; padding-bottom: 50px; }
        .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; }
        .container { padding: 20px; }
        .card { background: #1a1a1a; border-left: 4px solid #CC0000; padding: 15px; margin-bottom: 15px; }
        .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; border:none; width:100%; cursor:pointer; margin-top:10px; }
        .btn-choice { background: #333; margin-top: 20px; border: 1px solid #CC0000; }
    </style></head><body>
    <div class="bar">MONTSENY - <?php echo $local; ?></div>
    <div class="container">
        <?php if ( is_user_logged_in() ) : 
            $u = wp_get_current_user();
            $is_staff = current_user_can('montseny_tesorero') || current_user_can('manage_options');
        ?>
            <!-- PANTALLA DE ELECCIÓN PARA STAFF -->
            <?php if ($is_staff && !isset($_GET['view_as_member'])) : ?>
                <div style="text-align:center; padding: 20px 0;">
                    <h3>Hola, <?php echo $u->display_name; ?></h3>
                    <p>¿Qué quieres hacer hoy?</p>
                    <a href="?view_as_member=1" class="btn">VER MI CARNET</a>
                    <a href="<?php echo site_url('/montseny/gestion'); ?>" class="btn btn-choice">⚙️ PANEL DE GESTIÓN</a>
                </div>
            <?php else : ?>
                <!-- VISTA AFILIADO -->
                <div class="card">
                    <small style="color:#CC0000">CARNET SINDICAL</small>
                    <h2><?php echo $u->display_name; ?></h2>
                </div>
                <h3>Última Hora</h3>
                <?php foreach($feeds as $n): ?>
                    <div class="card">
                        <small style="color:#CC0000"><?php echo $n['f']; ?></small>
                        <a href="<?php echo $n['l']; ?>" target="_blank" style="color:#fff; text-decoration:none;"><h4><?php echo $n['t']; ?></h4></a>
                    </div>
                <?php endforeach; ?>
                <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" style="color:#444; display:block; text-align:center; margin-top:30px;">Cerrar Sesión</a>
            <?php endif; ?>

        <?php else : ?>
            <form method="post">
                <h3>Acceso Afiliados</h3>
                <input type="text" name="log" placeholder="Email" style="width:100%; padding:12px; margin-bottom:10px; box-sizing:border-box;">
                <input type="password" name="pwd" placeholder="Contraseña" style="width:100%; padding:12px; margin-bottom:10px; box-sizing:border-box;">
                <input type="hidden" name="m_login" value="1"><button type="submit" class="btn">ENTRAR</button>
            </form>
        <?php endif; ?>
    </div></body></html>
    <?php
}
