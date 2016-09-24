<?php

/**
 * Pukiwikiプラグイン::動画視聴 お知らせ表示
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewerPlugin
 * @package  Notifier
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

require_once "movieviewer.ini.php";
require_once "movieviewer_purchase_start.inc.php";

/**
 * プラグイン規定関数::初期化処理
 *
 * @return void
 */
function plugin_movieviewer_notify_user_init()
{
    plugin_movieviewer_set_global_settings();
}

/**
 * プラグイン規定関数::ブロック型で呼び出された場合の処理
 * 認証済みの場合: お知らせ画面を生成する
 * 未認証の場合: 何もしない
 *
 * 引数: string 購入申し込み(銀行振込)用ページ名
 *      string 購入申し込み(クレジットカード)用ページ名
 *
 * 例: #movieviewer_notify_user("購入申し込み_銀行","購入申し込み_クレジット");
 *
 * @return string 画面(html)
 */
function plugin_movieviewer_notify_user_convert()
{

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return '';
    }

    if ($user->isAdmin()) {
        return '';
    }

    $page_args = func_get_args();
    $params = array();
    $params['start_page_bank']   = $page_args[0];
    $params['start_page_credit'] = $page_args[1];

    $notifiers = array();
    $notifiers[] = new MovieViewerReportNotifier();
    $notifiers[] = new MovieViewerPurchaseOfferNotifier();
    $notifiers[] = new MovieViewerPurchaseStatusNotifier();
    
    $messages = array();
    foreach ($notifiers as $notifier) {
        $message = $notifier->generateMessage($user, $params);
        if ($message !== "") {
            $messages[] = $message;
        }
    }
    
    if (count($messages) === 0) {
        return '';
    }
    
    $messages_flat = implode("\r\n", $messages);

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    <h2>お知らせ</h2>
    <div class="movieviewer-notices">
    $messages_flat
    </div>
TEXT;

    return $content;
}

?>
