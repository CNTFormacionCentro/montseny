<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_dibujar_carnet($user_id) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'montseny_afiliados';
    $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE user_id = %d", $user_id));
    $u = get_userdata($user_id);
    $nombre_sindicato = get_option('montseny_nombre_local', 'CNT Ciudad Real');
    
    $fecha_alta = ($data && $data->fecha_alta) ? date('d/m/Y', strtotime($data->fecha_alta)) : '---';
    $codigo = ($data && $data->codigo_interno) ? $data->codigo_interno : 'F000VP0000';
    ?>
    <style>
        .carnet-wrapper { perspective: 1000px; margin: 20px auto; width: 340px; height: 210px; }
        .carnet-inner { position: relative; width: 100%; height: 100%; text-align: left; transition: transform 0.6s; transform-style: preserve-3d; cursor: pointer; }
        .carnet-wrapper.flipped .carnet-inner { transform: rotateY(180deg); }
        
        .card-face { position: absolute; width: 100%; height: 100%; backface-visibility: hidden; border-radius: 12px; box-shadow: 0 10px 20px rgba(0,0,0,0.5); overflow: hidden; }

        /* CARA FRONTAL (Negra) */
        .card-front { background: #000; color: #fff; display: flex; flex-direction: column; justify-content: flex-end; padding: 20px; box-sizing: border-box; }
        .card-front .diagonal { position: absolute; top: 0; left: 0; width: 150%; height: 150%; background: linear-gradient(135deg, rgba(204,0,0,0.3) 0%, transparent 40%); transform: rotate(-15deg); z-index: 1; }
        .card-front .flag { position: absolute; top: 15px; right: 15px; width: 45px; height: 28px; background: linear-gradient(135deg, #cc0000 50%, #000 50%); border: 1px solid #444; z-index: 2; }
        .card-front .logo-cnt { font-size: 5rem; font-weight: 900; line-height: 0.8; margin-bottom: 5px; position: relative; z-index: 2; }
        .card-front .slogan { font-size: 1.1rem; font-style: italic; margin-bottom: 5px; position: relative; z-index: 2; opacity: 0.9; }
        .card-front .full-name { font-size: 0.65rem; letter-spacing: 2px; position: relative; z-index: 2; opacity: 0.7; }

        /* CARA TRASERA (Gris) */
        .card-back { background: #cecece; color: #1a1a1a; transform: rotateY(180deg); padding: 20px; box-sizing: border-box; font-family: 'Courier New', Courier, monospace; display: flex; flex-direction: column; }
        .card-back .header-row { display: flex; justify-content: space-between; font-weight: bold; border-bottom: 1px solid #888; padding-bottom: 5px; margin-bottom: 15px; font-size: 1rem; }
        .card-back .data-row { font-size: 1rem; line-height: 1.4; flex-grow: 1; text-transform: uppercase; }
        .card-back .disclaimer { font-size: 0.6rem; text-align: center; border-top: 1px solid #888; padding-top: 5px; font-family: sans-serif; color: #444; }
    </style>

    <div class="carnet-wrapper" onclick="this.classList.toggle('flipped')">
        <div class="carnet-inner">
            <!-- FRONTAL -->
            <div class="card-face card-front">
                <div class="diagonal"></div>
                <div class="flag"></div>
                <div class="slogan">Un sindicato para luchar</div>
                <div class="logo-cnt">CNT</div>
                <div class="full-name">CONFEDERACIÓN NACIONAL DEL TRABAJO</div>
            </div>
            <!-- TRASERA -->
            <div class="face card-face card-back">
                <div class="header-row">
                    <span><?php echo esc_html($codigo); ?></span>
                    <span>ALTA: <?php echo esc_html($fecha_alta); ?></span>
                </div>
                <div class="data-row">
                    <br>
                    <strong><?php echo esc_html($u->display_name); ?></strong><br>
                    <?php echo esc_html($nombre_sindicato); ?><br>
                    <?php echo ($data) ? esc_html($data->ramo) : 'AFILIADE'; ?>
                </div>
                <div class="disclaimer">
                    El Carné Confederal acredita la afiliación a la CNT y la adhesión a los estatutos y acuerdos de la Organización.
                </div>
            </div>
        </div>
    </div>
    <p style="text-align:center; color:#666; font-size:0.8rem;">(Pulsa sobre el carnet para ver el reverso)</p>
    <?php
}
