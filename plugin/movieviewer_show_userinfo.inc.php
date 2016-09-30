<?php

/**
 * Pukiwikiプラグイン::動画視聴 ログインユーザ情報表示
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewerPlugin
 * @package  Auth
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

require_once "movieviewer.ini.php";

/**
 * プラグイン規定関数::初期化処理
 *
 * @return void
 */
function plugin_movieviewer_show_userinfo_init()
{
    plugin_movieviewer_set_global_settings();
}

/**
 * プラグイン規定関数::ブロック型で呼び出された場合の処理
 * 認証済みの場合: ユーザ情報画面を生成する
 * 未認証の場合: 何もしない
 *
 * 引数: なし
 *
 * @return string 画面(html)
 */
function plugin_movieviewer_show_userinfo_convert()
{
    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return '';
    }
    
    $hsc = "plugin_movieviewer_hsc";

    $content = <<<TEXT
        <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
        <div><span class="movieviewer-lead">{$hsc($user->describe())}様</span></div>
TEXT;

    return $content;
}

?>
