<?php

/**
 * Pukiwikiプラグイン::動画視聴 再視聴対象選択
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewerPlugin
 * @package  ReviewPackPurchase
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
function plugin_movieviewer_review_init()
{
    plugin_movieviewer_set_global_settings();
}

/**
 * プラグイン規定関数::ブロック型で呼び出された場合の処理
 * 認証済みの場合: 再視聴対象選択画面を生成する
 * 未認証の場合: エラー画面を生成する
 *
 * 引数: なし
 *
 * @return string 画面(html)
 */
function plugin_movieviewer_review_convert()
{
    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_convert_error_response("ログインが必要です。");
    }

    $plugin_args = func_get_args();

    try {
        plugin_movieviewer_review_assert_plugin_arguments($plugin_args);
    } catch (Exception $ex) {
        return plugin_movieviewer_convert_error_response("プラグインの引数が設定されていません。");
    }

    $start_page_bank   = $plugin_args[0];
    $start_page_credit = $plugin_args[1];

    $viewing_periods = plugin_movieviewer_get_viewing_periods_by_user_repository()->findById($user->id);
    $requests_not_yet_confirmed = plugin_movieviewer_get_review_pack_purchase_request_repository()->findNotYetConfirmed($user->id);

    $hsc = "plugin_movieviewer_hsc";

    // 期限の切れているものをリストアップ
    $viewing_periods = $viewing_periods->getExpiredPeriods();

    // コースごとに分類
    $viewing_periods_by_course = MovieViewerViewingPeriod::sortByCourse($viewing_periods);
    
    $courses = plugin_movieviewer_get_courses_repository()->find();
    $content_courses = "";
    foreach ($viewing_periods_by_course as $course_id => $periods) {

        $course = $courses->getCourse($course_id);

        $content_periods = "";
        foreach ($periods as $period) {

            if (MovieViewerReviewPackPurchaseRequest::requestsHasItem(
                $requests_not_yet_confirmed, $course_id, $period->session_id
            )) {
                continue;
            }

            $session = $course->getSession($period->session_id);
            $field_id = "{$hsc($course->id)}_{$hsc($session->id)}";
            $content_periods .=<<<TEXT
            <label class='movie-session' for="{$field_id}">{$session->describe()}</label>
            <input class='movie-session' type="checkbox" name="sessions" id="{$field_id}" value="{$field_id}">
TEXT;
        }

        if ($content_periods === "") {
            continue;
        }

        $content_course =<<<TEXT
        <div class='movieviewer-course'>
        <h3>{$hsc($course->name)}</h3>
        <fieldset style='border: none;'>
        {$content_periods}
        </fieldset>
        </div>
TEXT;

        $content_courses .= $content_course;
    }

    $uri_start_bank = plugin_movieviewer_get_script_uri() . "?{$start_page_bank}&purchase_method=bank";
    $uri_start_credit = plugin_movieviewer_get_script_uri() . "?{$start_page_credit}&purchase_method=credit";

    $buttons_payment =<<<TEXT
    <a href="${uri_start_bank}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>銀行振り込みで申し込み</a>
TEXT;

    $settings = plugin_movieviewer_get_global_settings();
    if ($settings->payment->isCreditEnabled()) {
        $buttons_payment .=<<<TEXT
        <a href="${uri_start_credit}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>クレジットカードで申し込み</a>
TEXT;
    }

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.0/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.12.0/themes/cupertino/jquery-ui.css" rel="stylesheet">
    <script src="plugin/movieviewer/assets/js/movieviewer_review.js"></script>
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    <link href="plugin/movieviewer/assets/css/movieviewer_review.css" rel="stylesheet">
    <style>
    </style>

    <h2>再視聴可能な単元</h2>

    <p>
    再視聴したい単元を選択して、申し込みボタンを押してください。
    </p>

    ${content_courses}

    <div style="margin-top:10px;">
    $buttons_payment
    </div>
TEXT;

    return $content;
}

/*-- 以下、内部処理 --*/

/**
 * プラグインの引数が正しいかどうかを検査し、問題がある場合は例外を発生させる
 *
 * @param array $args プラグインの引数
 * 
 * @return void
 */
function plugin_movieviewer_review_assert_plugin_arguments($args)
{
    if (count($args) !== 2) {
        throw new Exception();
    }

    if ($args[0] === "" || $args[1] === "") {
        throw new Exception();
    }
}

?>