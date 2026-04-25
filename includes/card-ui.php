<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_dibujar_carnet($user_id) {
    global $wpdb;
    $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}montseny_afiliados WHERE user_id = %d", $user_id));
    $u = get_userdata($user_id);
    $nombre_sindicato = get_option('montseny_nombre_local', 'Sindicato de Oficios Varios');
    
    // Formatear fecha
    $fecha = ($data && $data->fecha_alta) ? date('d/m/Y', strtotime($data->fecha_alta)) : '---';
    $codigo = ($data && $data->codigo_interno) ? $data->codigo_interno : 'SIN CÓDIGO';
    ?>
    <style>
        .carnet-container { perspective: 1000px; margin-bottom: 30px; }
        .carnet-card { width: 100%; max-width: 350px; height: 210px; position: relative; transition: transform 0.6s; transform-style: preserve-3d; margin: auto; cursor: pointer; }
        .carnet-card.flipped { transform: rotateY(180deg); }
        
        .face { position: absolute; width: 100%; height: 100%; backface-visibility: hidden; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        
        /* CARA FRONTAL (Basada en tu foto 1) */
        .front { background: #000; color: #fff; display: flex; flex-direction: column; justify-content: center; align-items: flex-end; padding: 20px; box-sizing: border-box; }
        .front::before { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(204,0,0,0.2) 0%, transparent 50%); }
        .front .cnt-logo { font-size: 4rem; font-weight: 900; line-height: 1; margin-right: 10px; }
        .front .slogan { font-size: 1rem; margin-bottom: 5px; opacity: 0.9; margin-right: 10px; }
        .front .confederal { font-size: 0.6rem; letter-spacing: 1px; opacity: 0.7; margin-right: 10px; }
        .flag-mini { position: absolute; top: 15px; right: 15px; width: 40px; height: 25px; background: linear-gradient(135deg, #cc0000 50%, #000 50%); border: 1px solid #fff; }

        /* CARA TRASERA (Basada en tu foto 2) */
        .back { background: #bbb; color: #000; transform: rotateY(180deg); padding: 15px; box-sizing: border-box; display: flex; flex-direction: column; }
        .back-header { display: flex; justify-content: space-between; font-weight: bold; font-family: monospace; border-bottom: 1px solid #999; padding-bottom: 5px; margin-bottom: 10px; }
        .back-body { font-size: 0.9rem; line-height: 1.4; flex-grow: 1; }
        .back-footer { font-size: 0.7rem; color: #444; border-top: 1px solid #999; padding-top: 5px; text-align: center; }
    </style>

    <div class="carnet-container" onclick="this.querySelector('.carnet-card').classList.toggle('flipped')">
        <div class="carnet-card">
            <!-- FRONTAL -->
            <div class="face front">
                <div class="flag-mini"></div>
                <div class="slogan">Un sindicato para luchar</div>
                <div class="cnt-logo">CNT</div>
                <div class="confederal">CONFEDERACIÓN NACIONAL DEL TRABAJO</div>
            </div>
            <!-- TRASERA -->
            <div class="face back">
                <div class="back-header">
                    <span><?php echo $codigo; ?></span>
                    <span>Alta: <?php echo $fecha; ?></span>
                </div>
                <div class="back-body">
                    <p><strong><?php echo esc_html($u->display_name); ?></strong><br>
                    <?php echo $nombre_sindicato; ?><br>
                    <?php echo esc_html($data->ramo); ?></p>
                </div>
                <div class="back-footer">
                    El Carné Confederal acredita la afiliación a la CNT
                </div>
            </div>
        </div>
        <p style="text-align:center; font-size:0.7rem; color:#666; margin-top:10px;">(Pulsa para girar el carnet)</p>
    </div>
    <?php
}
