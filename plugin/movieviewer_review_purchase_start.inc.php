<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_review_purchase_start_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_review_purchase_start_convert(){

    $purchase_method = filter_input(INPUT_GET, "purchase_method");
    $sessions = filter_input(INPUT_GET, "sessions");

    $content =<<<TEXT
    ${purchase_method}
    ${sessions}
TEXT;

    return $content;
}

function plugin_movieviewer_review_purchase_start_action(){
    return "hoge";
}

?>