<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_review_purchase_confirm_payment_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_review_purchase_confirm_payment_convert(){
    return "hoge";
}

function plugin_movieviewer_review_purchase_confirm_payment_action(){
    return "hoge";
}
?>