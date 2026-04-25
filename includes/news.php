<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_get_feeds() {
    $feeds = array();
    $url_l = get_option('montseny_url_local', 'https://ciudadreal.cnt.es');
    $url_c = get_option('montseny_url_confederal', 'https://www.cnt.es');

    // 1. Web Local (vía API si es WP)
    $response = wp_remote_get( trailingslashit($url_l) . 'wp-json/wp/v2/posts?_embed&per_page=2' );
    if ( !is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200 ) {
        $posts = json_decode(wp_remote_retrieve_body($response));
        foreach($posts as $p) {
            $img = isset($p->_embedded->{'wp:featuredmedia'}[0]->source_url) ? $p->_embedded->{'wp:featuredmedia'}[0]->source_url : '';
            $feeds[] = array('f'=>'CIUDAD REAL', 't'=>$p->title->rendered, 'l'=>$p->link, 'i'=>$img);
        }
    }

    // 2. Confederal (vía RSS)
    include_once( ABSPATH . WPINC . '/feed.php' );
    $rss = fetch_feed( trailingslashit($url_c) . 'feed/' );
    if ( ! is_wp_error( $rss ) ) {
        foreach($rss->get_items(0, 2) as $item) {
            $content = $item->get_content();
            preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $content, $m);
            $img = isset($m['src']) ? $m['src'] : '';
            $feeds[] = array('f'=>'CNT.ES', 't'=>$item->get_title(), 'l'=>$item->get_permalink(), 'i'=>$img);
        }
    }
    return $feeds;
}
