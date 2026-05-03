<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_render_app() {
    if (isset($_POST['m_login'])) {
        $u = wp_signon(['user_login'=>$_POST['log'],'user_password'=>$_POST['pwd'],'remember'=>true], false);
        wp_redirect(site_url(is_wp_error($u) ? '/montseny/?err=1' : '/montseny/')); exit;
    }
    
    $local = get_option('montseny_nombre_local', 'CNT');
    $u = wp_get_current_user();
    $is_staff = current_user_can('montseny_tesorero') || current_user_can('montseny_comunica') || current_user_can('manage_options');
    ?>
    <!DOCTYPE html><html lang="es"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Montseny</title>
    
    <!-- Enlace al servidor de archivos PWA -->
    <link rel="manifest" href="<?php echo site_url('/montseny-manifest.json'); ?>">
    <meta name="theme-color" content="#CC0000">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <style>
        body { font-family: sans-serif; background: #000; color: #fff; margin: 0; padding-bottom: 50px; }
        .bar { background: #CC0000; padding: 20px; text-align: center; font-weight: bold; position: sticky; top:0; z-index:99; }
        .container { padding: 15px; }
        .btn { background: #CC0000; color: #fff; display: block; text-align: center; padding: 15px; text-decoration: none; border-radius: 8px; font-weight: bold; border:none; width:100%; cursor:pointer; margin-top:10px; }
        input { width: 100%; padding: 15px; margin-bottom: 10px; background: #222; color: #fff; border: 1px solid #333; border-radius: 8px; box-sizing:border-box; }
    </style>

    <script>
        // Registro del Motor
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?php echo site_url('/montseny-sw.js'); ?>')
                .then(() => console.log('Montseny PWA: Motor encendido'));
            });
        }
    </script>
    </head><body>
    <div class="bar"><?php echo $local; ?></div>
    <div class="container">
        <?php if ( is_user_logged_in() ) : ?>
            <?php if ($is_staff && !isset($_GET['view_as_member'])) : ?>
                <div style="text-align:center; padding: 40px 0;">
                    <h2>Salud, <?php echo $u->display_name; ?></h2>
                    <a href="?view_as_member=1" class="btn">VER MI CARNET</a>
                    <?php if (current_user_can('montseny_tesorero') || current_user_can('manage_options')) : ?>
                        <a href="<?php echo site_url('/montseny/gestion'); ?>" class="btn" style="background:#333;">⚙️ GESTIÓN SINDICAL</a>
                    <?php endif; ?>
                    <a href="<?php echo wp_logout_url(site_url('/montseny')); ?>" style="color:#666; display:block; margin-top:40px; text-decoration:none;">Cerrar Sesión</a>
                </div>
            <?php else : ?>
                <?php montseny_dibujar_carnet($u->ID); ?>
                <a href="<?php echo site_url('/montseny/'); ?>" style="color:#666; display:block; text-align:center; margin-top:20px; text-decoration:none; font-size:0.8rem;">← Volver</a>
            <?php endif; ?>
        <?php else : ?>
            <form method="post"><h3 style="text-align:center;">Acceso Afiliados</h3><input type="text" name="log" placeholder="Email" required><input type="password" name="pwd" placeholder="Contraseña" required><input type="hidden" name="m_login" value="1"><button type="submit" class="btn">ENTRAR</button></form>
        <?php endif; ?>
    </div></body></html>
    <?php
}
