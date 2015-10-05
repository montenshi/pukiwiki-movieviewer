<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_logout_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_logout_convert() {
    return '';
}

function plugin_movieviewer_logout_action() {
    plugin_movieviewer_get_auth_manager()->logout();
    return array("msg"=>"動画配信会員ログアウト", "body"=>"ログアウトが完了しました。");
}

?>