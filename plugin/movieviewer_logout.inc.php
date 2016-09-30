<?php

/**
 * Pukiwikiプラグイン::動画視聴 ログアウト
 * movieviewer_auth でログインしたユーザが対象
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
function plugin_movieviewer_logout_init()
{
    plugin_movieviewer_set_global_settings();
}

/**
 * プラグイン規定関数::ブロック型で呼び出された場合の処理
 * プラグインとして convert は必須なので定義している
 *
 * @return string 空文字
 */
function plugin_movieviewer_logout_convert() 
{
    return '';
}

/**
 * プラグイン規定関数::アクション型で呼び出された場合の処理
 * ログアウトする
 *
 * 例: http://host:port/index.php?cmd=movieviewer_logout
 * 
 * @return array ページ名、画面(html)
 */
function plugin_movieviewer_logout_action()
{
    plugin_movieviewer_get_auth_manager()->logout();
    return array("msg"=>'会員ログアウト', "body"=>"ログアウトしました。");
}

?>