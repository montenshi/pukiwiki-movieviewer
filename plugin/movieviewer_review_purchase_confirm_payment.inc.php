<?php

/**
 * Pukiwikiプラグイン::動画視聴 再視聴申し込み入金確認
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
function plugin_movieviewer_review_purchase_confirm_payment_init()
{
    plugin_movieviewer_set_global_settings();
}

/**
 * プラグイン規定関数::ブロック型で呼び出された場合の処理
 * 認証済みの場合: 入金確認画面を生成する
 * 未認証の場合: エラー画面を生成する
 *
 * 引数: なし
 *
 * @return string 画面(html)
 */
function plugin_movieviewer_review_purchase_confirm_payment_convert()
{

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_convert_error_response("管理者でログインする必要があります。");
    }

    if (!$user->isAdmin()) {
        return plugin_movieviewer_convert_error_response("管理者でログインする必要があります。");
    }

    $requests = plugin_movieviewer_get_review_pack_purchase_request_repository()->findNotYetConfirmed();

    if (count($requests) === 0) {
        $content = <<<TEXT
        <h2>入金確認</h2>
        <p>入金確認が必要なデータはありません。</p>
TEXT;
        return $content;
    }

    $content_requests = plugin_movieviewer_review_purchase_confirm_payment_render_requests($requests);

    $page = plugin_movieviewer_get_current_page();
    $action_url = plugin_movieviewer_get_script_uri() . "?cmd=movieviewer_review_purchase_confirm_payment&page=$page";

    $date_begin_cds = plugin_movieviewer_review_purchase_confirm_payment_render_begin_date_candidates();

    $hsc = "plugin_movieviewer_hsc";
    $input_csrf_token = "plugin_movieviewer_generate_input_csrf_token";

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.0/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.12.0/themes/cupertino/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    <script src="plugin/movieviewer/assets/css/movieviewer_purchase_confirm_payment.js"></script>
    <h2>入金確認</h2>
    <p>
      入金が確認できたものにチェックを付けて、確認ボタンを押してください。
    </p>
    <h4>確認対象</h4>
    <p>
    <form action="{$action_url}" method="POST">
    <input type="hidden" name="ope_type" value="confirm">
    {$input_csrf_token()}
    $content_requests
    </p>
    <p>
    <h4>視聴開始日</h4>
    <div id="radio">
    <p>
    {$date_begin_cds}
    </p>
    </div>
    </p>
    <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">確認</button>
    </form>
TEXT;

    return $content;
}

/**
 * プラグイン規定関数::アクション型で呼び出された場合の処理
 * パラメータ ope_type の値により、以下の処理を行う
 *   confirm: 入金確定対象の申し込み一覧画面を生成する
 *   execute: 入金を確定させ、結果画面を生成する
 * 
 * 引数: string ope_type 処理区分
 *      string purchase_requests 対象の申し込みIDの一覧(カンマ区切り)
 * 
 * 注意: 単独で呼び出さないこと(convertの画面と連携している)

 * @return array ページ名、画面(html)
 */
function plugin_movieviewer_review_purchase_confirm_payment_action()
{

    $page = plugin_movieviewer_get_current_page();

    try {
        plugin_movieviewer_validate_csrf_token();
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_action_error_response($page, "不正なリクエストです。");
    }

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_action_error_response($page, "管理者でログインする必要があります。");
    }

    if (!$user->isAdmin()) {
        return plugin_movieviewer_action_error_response($page, "管理者でログインする必要があります。");
    }

    $ope_type = filter_input(INPUT_POST, 'ope_type');

    if ($ope_type === 'confirm') {
        return plugin_movieviewer_review_purchase_confirm_payment_action_confirm();
    }

    if ($ope_type === 'execute') {
        return plugin_movieviewer_review_purchase_confirm_payment_action_execute();
    }

    return plugin_movieviewer_action_error_response($page, "処理ができません。最初からやり直してください。");
}

/*-- 以下、内部処理 --*/

/**
 * [ブロック] 視聴開始日の選択肢を生成する
 *
 * @return string 選択肢(radio)
 */
function plugin_movieviewer_review_purchase_confirm_payment_render_begin_date_candidates()
{
    // 選択は明後日のみ
    $date_begin = plugin_movieviewer_now();
    $date_begin->modify("+2 day");
    $value = $date_begin->format("Y-m-d");
    $id = "viewing_period_date_begin_{$value}";
    $date_begin_cds =<<<TEXT
        <input type=radio id="$id" name="viewing_perod_date_begin" value="{$value}" checked><label for="$id">{$date_begin->format("m月d日")}から</label>
TEXT;
    return $date_begin_cds;
}

/**
 * [ブロック] 確定対象候補となる申し込み一覧を生成する
 *
 * @param array $requests 申し込み(MovieViewerReviewPackPurchaseRequest)
 *
 * @return string 申し込み一覧(html)
 */
function plugin_movieviewer_review_purchase_confirm_payment_render_requests($requests)
{

    $hsc = "plugin_movieviewer_hsc";

    usort($requests, "MovieViewerReviewPackPurchaseRequest::compareByMemberId");

    $content_rows = "";
    foreach ($requests as $request) {

        $ctrl_value = $hsc($request->getId());
        $ctrl_id = $hsc("pr_{$ctrl_value}");

        $content_row =<<<TEXT
        <tr>
          <td><input type="checkbox" name="purchase_requests[]" value="{$ctrl_value}" id="{$ctrl_id}"></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getUser()->memberId)}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getUser()->lastName)} {$hsc($request->getUser()->firstName)}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getUser()->id)}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->describePack())}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getDateRequested()->format("Y/m/d H:m:s"))}</label></td>
        </tr>
TEXT;

        $content_rows .= $content_row;
    }

    $content =<<<TEXT
    <div>
        <table class="table purchase-requests">
          <thead>
          <tr>
            <th></th>
            <th>会員番号</th>
            <th>名前</th>
            <th>メールアドレス</th>
            <th>対象</th>
            <th>申込日</th>
          </tr>
          </thead>
          <tbody>
            {$content_rows}
          <tbody>
        </table>
    </div>
TEXT;

    return $content;
}

/**
 * [アクション] 入金確定対象の受講申し込み一覧画面を生成する
 *
 * @return array ページ名, 画面(html)
 */
function plugin_movieviewer_review_purchase_confirm_payment_action_confirm()
{

    $page = plugin_movieviewer_get_current_page();

    $ids = filter_input(INPUT_POST, 'purchase_requests', FILTER_DEFAULT , FILTER_REQUIRE_ARRAY);

    foreach ($ids as $req_id) {
        try {
            plugin_movieviewer_validate_review_pack_request_id($req_id);
        } catch (MovieViewerValidationException $ex) {
            return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
        }
    }

    $requests = array();
    foreach ($ids as $req_id) {
        try {
            $request = plugin_movieviewer_get_review_pack_purchase_request_repository()->findById($req_id);
        } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
            return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
        }
        $requests[] = $request;
    }
    
    if (count($requests) === 0) {
        return plugin_movieviewer_action_error_response($page, "申し込みが見つかりません。");
    }

    $date_begin = filter_input(INPUT_POST, 'viewing_perod_date_begin', FILTER_DEFAULT);

    try {
        plugin_movieviewer_validate_ymd($date_begin);
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
    }
    
    $date_begin = new DateTime($date_begin);

    $settings = plugin_movieviewer_get_global_settings();
    $service = new MovieViewerReviewPackPurchaseConfirmationService($settings);

    usort($requests, "MovieViewerReviewPackPurchaseRequest::compareByMemberId");

    $content_rows = "";
    foreach ($requests as $request) {

        $ctrl_value = $hsc($request->getId());
        $ctrl_id = $hsc("pr_{$ctrl_value}");

        $confirmation = $service->preConfirm($request, $date_begin);

        $content_row =<<<TEXT
        <tr>
          <input type="hidden" name="purchase_requests[]" value="{$ctrl_value}" id="{$ctrl_id}">
          <td><label for="{$ctrl_id}">{$hsc($request->getUser()->memberId)}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getUser()->lastName)} {$hsc($request->getUser()->firstName)}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getUser()->id)}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->describePack())}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getDateRequested()->format("Y/m/d H:m:s"))}</label></td>
          <td>{$hsc($confirmation->getViewingPeriod()->date_begin->format("Y/m/d"))}</td>
          <td>{$hsc($confirmation->getViewingPeriod()->date_end->format("Y/m/d"))}</td>
        </tr>
TEXT;

        $content_rows .= $content_row;
    }

    $content_requests =<<<TEXT
    <div>
        <table class="table purchase-requests">
          <thead>
          <tr>
            <th>会員番号</th>
            <th>名前</th>
            <th>メールアドレス</th>
            <th>対象</th>
            <th>申込日</th>
            <th>視聴期限(開始)</th>
            <th>視聴期限(終了)</th>
          </tr>
          </thead>
          <tbody>
            {$content_rows}
          <tbody>
        </table>
    </div>
TEXT;

    $hsc = "plugin_movieviewer_hsc";
    $input_csrf_token = "plugin_movieviewer_generate_input_csrf_token";
    $action_url = plugin_movieviewer_get_script_uri() . "?cmd=movieviewer_review_purchase_confirm_payment&page=$page";

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.12.0/themes/cupertino/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    <h2>入金確認(最終確認)</h2>
    <p>
      以下の申し込みの入金を確定します。確認の上、確定ボタンを押してください。
    </p>
    <h4>確定対象</h4>
    <p>
    <form action="{$action_url}" method="POST">
    <input type="hidden" name="ope_type" value="execute">
    {$input_csrf_token()}
    $content_requests
    <input type=hidden name="viewing_perod_date_begin" value="{$date_begin->format("Y-m-d")}">
    </p>
    <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">確定</button>
    </form>
TEXT;

    return array('msg'=>$page, 'body'=>$content);
    
}

/**
 * [アクション] 入金を確定させ、結果画面を生成する
 *
 * @return array ページ名, 画面(html)
 */
function plugin_movieviewer_review_purchase_confirm_payment_action_execute()
{

    $page = plugin_movieviewer_get_current_page();

    try {
        plugin_movieviewer_validate_csrf_token();
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_action_error_response($page, "不正なリクエストです。");
    }

    $ids = filter_input(INPUT_POST, 'purchase_requests', FILTER_DEFAULT , FILTER_REQUIRE_ARRAY);

    foreach ($ids as $req_id) {
        try {
            plugin_movieviewer_validate_review_pack_request_id($req_id);
        } catch (MovieViewerValidationException $ex) {
            return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
        }
    }

    $requests = array();
    foreach ($ids as $req_id) {
        try {
            $request = plugin_movieviewer_get_review_pack_purchase_request_repository()->findById($req_id);
        } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
            return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
        }
        $requests[] = $request;
    }
    
    if (count($requests) === 0) {
        return plugin_movieviewer_action_error_response($page, "申し込みが見つかりません。");
    }

    $date_begin = filter_input(INPUT_POST, 'viewing_perod_date_begin', FILTER_DEFAULT);
    try {
        plugin_movieviewer_validate_ymd($date_begin);
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
    }
    $date_begin = new DateTime($date_begin);

    $settings = plugin_movieviewer_get_global_settings();
    $service = new MovieViewerReviewPackPurchaseConfirmationService($settings);

    $hsc = "plugin_movieviewer_hsc";

    $content_rows = "";
    foreach ($requests as $request) {
        $confirmation = $service->confirm($request, $date_begin);

        $content_row =<<<TEXT
        <tr>
          <td>{$hsc($request->getUser()->lastName)} {$hsc($request->getUser()->firstName)}</td>
          <td>{$hsc($request->getUser()->id)}</td>
          <td>{$hsc($request->describePack())}</td>
          <td>{$hsc($request->getDateRequested()->format("Y/m/d H:m:s"))}</td>
          <td>{$hsc($confirmation->getViewingPeriod()->date_begin->format("Y/m/d"))}</td>
          <td>{$hsc($confirmation->getViewingPeriod()->date_end->format("Y/m/d"))}</td>
        </tr>
TEXT;
        $content_rows .= $content_row;
    }

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.12.0/themes/cupertino/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    <h2>入金確認完了</h2>
    <p>
      以下の申し込みの入金を確定しました。
    </p>
    <p>
    <table class="table purchase-requests">
      <thead>
      <tr>
        <th>名前</th>
        <th>メールアドレス</th>
        <th>受講対象</th>
        <th>申込日</th>
        <th>視聴期限(開始)</th>
        <th>視聴期限(終了)</th>
      </tr>
      </thead>
      <tbody>
        {$content_rows}
      <tbody>
    </table>
    </p>
TEXT;

    return array('msg'=>$page, 'body'=>$content);
}

?>