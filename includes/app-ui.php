<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_render_app() {
    // Manejo de Login interno
    if (isset($_POST['m_login'])) {
        $u = wp_signon(array('user_login'=>$_POST['log'],'user_password'=>$_POST['pwd'],'remember'=>true), false);
        wp_redirect(site_url(is_wp_error($u) ? '/montseny/?err=1' : '/montseny/')); 
        exit;
    }

    $local = get_option('montseny_nombre_local', 'CNT');
    $feeds = montseny_get_feeds();
    $u = wp_get_current_user();
    $is_staff = current_user_can('montseny_tesorero') || current_user_can('montseny_comunica') || current_user_can('manage_options');
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Montseny</title>
    <style>
        body { font-family: sans-serif; background: #000; color: #fff; margin: 0; padding-bottom: 50px; }
        .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; position: sticky; top:0; z-index:99; }
        .container { padding: 15px; }
        .card-news { background: #1a1a1a; border-radius: 8px; margin-bottom: 15px; overflow: hidden; display: flex; border-left: 4px solid #CC0000; }
        .news-img { width: 80px; height: 80px; background-size: cover; background-position: center; flex-shrink: 0; }
        .news-txt { padding: 10px; }
        .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; border:none; width:100%; cursor:pointer; margin-top:10px; }
        .btn-alt { background: #222; border: 1px solid #444; font-size: 0.8rem; color: #aaa; }
    </style></head><body>
    <div class="bar"><?php echo $local; ?></div>
    <div class="container">
        <?php if ( is_user_logged_in() ) : ?>
            <?php if ($is_staff && !isset($_GET['view_as_member'])) : ?>
                <div style="text-align:center; padding: 40px 0;">
                    <h2>Salud, <?php echo $u->display_name; ?></h2>
                    <p>Has entrado como gestión.</p>
                    <a href="?view_as_member=1" class="btn">VER MI CARNET</a>
                    <?php if (current_user_can('montseny_tesorero') || current_user_can('manage_options')) : ?>
                        <a href="<?php echo site_url('/montseny/gestion'); ?>" class="btn" style="background:#333;">⚙️ PANEL DE GESTIÓN</a>
                    <?php endif; ?>
                    <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" class="btn btn-alt">Cerrar Sesión</a>
                </div>
            <?php else : ?>
                <?php montseny_dibujar_carnet($u->ID); ?>
                
                <?php if($is_staff): ?>
                    <a href="<?php echo site_url('/montseny/'); ?>" class="btn btn-alt" style="margin-bottom:20px;">← Volver a elección de rol</a>
                <?php endif; ?>

                <h3>Última Hora</h3>
                <?php foreach($feeds as $n): ?>
                    <a href="<?php echo $n['l']; ?>" target="_blank" style="text-decoration:none; color:inherit;">
                    <div class="card-news">
                        <?php if(!empty($n['i'])): ?><div class="news-img" style="background-image:url('<?php echo $n['i']; ?>')"></div><?php endif; ?>
                        <div class="news-txt"><small style="color:#CC0000"><?php echo $n['f']; ?></small><h4><?php echo $n['t']; ?></h4></div>
                    </div></a>
                <?php endforeach; ?>
                <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" class="btn btn-alt">Cerrar Sesión</a>
            <?php endif; ?>
        <?php else : ?>
            <form method="post"><h3 style="text-align:center;">Acceso Afiliados</h3><input type="text" name="log" placeholder="Email o Usuario" style="width:100%; padding:15px; margin-bottom:10px; background:#222; color:#fff; border:none; border-radius:8px; box-sizing:border-box;"><input type="password" name="pwd" placeholder="Contraseña" style="width:100%; padding:15px; margin-bottom:10px; background:#222; color:#fff; border:none; border-radius:8px; box-sizing:border-box;"><input type="hidden" name="m_login" value="1"><button type="submit" class="btn">ENTRAR</button></form>
        <?php endif; ?>
    </div></body></html>
    <?php
}
