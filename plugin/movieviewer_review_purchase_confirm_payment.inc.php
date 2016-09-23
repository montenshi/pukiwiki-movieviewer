<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_review_purchase_confirm_payment_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_review_purchase_confirm_payment_convert(){

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_convert_error_response("管理者でログインする必要があります。");
    }

    if (!$user->isAdmin()) {
        return plugin_movieviewer_convert_error_response("管理者でログインする必要があります。");
    }

    $content_requests = plugin_movieviewer_review_purchase_confirm_payment_render_requests();

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

function plugin_movieviewer_review_purchase_confirm_payment_render_begin_date_candidates() {
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

function plugin_movieviewer_review_purchase_confirm_payment_render_requests() {

    $hsc = "plugin_movieviewer_hsc";

    $requests = plugin_movieviewer_get_review_pack_purchase_request_repository()->findNotYetConfirmed();

    if (count($requests) === 0) {
        $content = <<<TEXT
        <h2>入金確認</h2>
        <p>入金確認が必要なデータはありません。</p>
TEXT;
        return $content;
    }
    
    usort($requests, "MovieViewerReviewPackPurchaseRequest::compareByMemberId");

    $content_rows = "";
    foreach($requests as $request) {

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

function plugin_movieviewer_review_purchase_confirm_payment_action(){

    $page = plugin_movieviewer_get_current_page();

    $content =<<<TEXT
    hoge
TEXT;

    return array('msg'=>$page, 'body'=>$content);
}

?>