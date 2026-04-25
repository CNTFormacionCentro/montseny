<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function montseny_get_feeds() {
    $feeds = array();
    
    // 1. Web Local (Ciudad Real)
    $locales = get_posts(array('numberposts' => 2));
    foreach($locales as $post) {
        $feeds[] = array('f' => 'Web Local', 't' => $post->post_title, 'l' => get_permalink($post->ID));
    }

    // 2. Web Confederal (cnt.es)
    include_once( ABSPATH . WPINC . '/feed.php' );
    $rss = fetch_feed('https://www.cnt.es/feed/');
    if (!is_wp_error($rss)) {
        foreach($rss->get_items(0, 2) as $item) {
            $feeds[] = array('f' => 'Confederal', 't' => $item->get_title(), 'l' => $item->get_permalink());
        }
    }

    // 3. Telegram Público
    $tg = get_option('montseny_telegram_alias', 'cnt_nacional');
    $res = wp_remote_get("https://t.me/s/" . $tg);
    if (!is_wp_error($res)) {
        $body = wp_remote_retrieve_body($res);
        if (preg_match_all('/<div class="tgme_widget_message_text[^>]*>(.*?)<\/div>/s', $body, $m)) {
            $feeds[] = array('f' => 'Telegram', 't' => wp_trim_words(strip_tags(end($m[1])), 15), 'l' => "https://t.me/s/".$tg);
        }
    }
    return $feeds;
}
