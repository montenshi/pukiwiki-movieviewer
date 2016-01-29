<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_show_userinfo_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_show_userinfo_convert() {

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return '';
    }
    
    $hsc = "plugin_movieviewer_hsc";

    $content = <<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <div><span class="movieviewer-lead">{$hsc($user->describe())}様</span></div>
TEXT;

    return $content;
}

?>
