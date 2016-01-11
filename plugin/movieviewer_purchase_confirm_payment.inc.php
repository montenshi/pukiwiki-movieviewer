<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_purchase_confirm_payment_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_purchase_confirm_payment_convert() {

    $hsc = "plugin_movieviewer_hsc";

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_convert_error_response("管理者でログインする必要があります。");
    }

    if (!$user->isAdmin()) {
        return plugin_movieviewer_convert_error_response("管理者でログインする必要があります。");
    }

    $requests = plugin_movieviewer_get_deal_pack_purchase_request_repository()->findAll();

    $requestsNotConfirmed = array();
    foreach($requests as $request) {
        if ($request->isPaymentConfirmed()) {
            continue;
        }

        $requestsNotConfirmed[] = $request;
    }

    if (count($requestsNotConfirmed) === 0) {
        $content = <<<TEXT
        <h2>入金確認</h2>
        <p>入金確認が必要なデータはありません。</p>
TEXT;
        return $content;
    }

    usort($requestsNotConfirmed, "MovieViewerDealPackPurchaseRequest::compareByMemberId");

    $content_rows = "";
    foreach($requestsNotConfirmed as $request) {

        $ctrl_value = $hsc($request->getId());
        $ctrl_id = $hsc("pr_{$ctrl_value}");

        $content_row =<<<TEXT
        <tr>
          <td><input type="checkbox" name="purchase_requests[]" value="{$ctrl_value}" id="{$ctrl_id}"></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getUser()->memberId)}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getUser()->lastName)} {$hsc($request->getUser()->firstName)}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getUser()->id)}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getPack()->describe())}</label></td>
          <td><label for="{$ctrl_id}">{$hsc($request->getDateRequested()->format("Y/m/d H:m:s"))}</label></td>
        </tr>
TEXT;

        $content_rows .= $content_row;
    }

    $content_list =<<<TEXT
    <div>
        <table class="table purchase-requests">
          <thead>
          <tr>
            <th></th>
            <th>会員番号</th>
            <th>名前</th>
            <th>メールアドレス</th>
            <th>受講対象</th>
            <th>申込日</th>
          </tr>
          </thead>
          <tbody>
            {$content_rows}
          <tbody>
        </table>
    </div>
TEXT;

    $page = plugin_movieviewer_get_current_page();
    $action_url = plugin_movieviewer_get_script_uri() . "?cmd=movieviewer_purchase_confirm_payment&page=$page";
    $input_csrf_token = "plugin_movieviewer_generate_input_csrf_token";

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>入金確認</h2>
    <p>
      入金が確認できたものにチェックを付けて、確認ボタンを押してください。
    </p>
    <p>
    <form action="{$action_url}" method="POST">
    <input type="hidden" name="ope_type" value="confirm">
    {$input_csrf_token()}
    $content_list
    </p>
    <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">確認</button>
    </form>
TEXT;

    return $content;
}

function plugin_movieviewer_purchase_confirm_payment_action() {

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
        return plugin_movieviewer_purchase_confirm_payment_action_confirm();
    }

    if ($ope_type === 'execute') {
        return plugin_movieviewer_purchase_confirm_payment_action_execute();
    }

    return plugin_movieviewer_action_error_response($page, "処理ができません。最初からやり直してください。");
}

function plugin_movieviewer_purchase_confirm_payment_action_confirm() {

    $ids = filter_input(INPUT_POST, 'purchase_requests', FILTER_DEFAULT , FILTER_REQUIRE_ARRAY);

    foreach($ids as $req_id) {
        try {
            plugin_movieviewer_validate_deal_pack_request_id($req_id);
        } catch (MovieViewerValidationException $ex) {
            return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
        }
    }

    $requests = array();
    foreach($ids as $req_id) {
        try {
            $request = plugin_movieviewer_get_deal_pack_purchase_request_repository()->findById($req_id);
        } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
            return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
        }
        $requests[] = $request;
    }

    $hsc = "plugin_movieviewer_hsc";

    foreach($requests as $request) {

        $ctrl_value = $hsc($request->getId());
        $ctrl_id = $hsc("pr_{$ctrl_value}");

        if (!$request->isPaymentConfirmed()) {
            $confirmation = $request->preConfirmPayment();
        } else {
            $confirmation = $request->getPaymentConfirmation();
        }

        $content_row =<<<TEXT
        <tr>
          <input type="hidden" name="purchase_requests[]" value="{$ctrl_value}" id="{$ctrl_id}">
          <td>{$hsc($request->getUser()->lastName)} {$hsc($request->getUser()->firstName)}</td>
          <td>{$hsc($request->getUser()->id)}</td>
          <td>{$hsc($request->getPack()->describe())}</td>
          <td>{$hsc($request->getDateRequested()->format("Y/m/d H:m:s"))}</td>
          <td>{$hsc($confirmation->getViewingPeriod()->date_begin->format("Y/m/d"))}</td>
          <td>{$hsc($confirmation->getViewingPeriod()->date_end->format("Y/m/d"))}</td>
        </tr>
TEXT;
        $content_rows .= $content_row;
    }

    $action_url = plugin_movieviewer_get_script_uri() . "?cmd=movieviewer_purchase_confirm_payment";

    $input_csrf_token = "plugin_movieviewer_generate_input_csrf_token";

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>入金確認(最終確認)</h2>
    <p>
      以下の申し込みの入金を確定します。確認の上、確定ボタンを押してください。
    </p>
    <p>
    <form action="{$action_url}" method="POST">
    <input type="hidden" name="ope_type" value="execute">
    {$input_csrf_token()}
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
    <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">確定</button>
    </form>
TEXT;

    $page = plugin_movieviewer_get_current_page();
    return array('msg'=>$page, 'body'=>$content);
}

function plugin_movieviewer_purchase_confirm_payment_action_execute() {

    $page = plugin_movieviewer_get_current_page();

    try {
        plugin_movieviewer_validate_csrf_token();
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_action_error_response($page, "不正なリクエストです。");
    }

    $ids = filter_input(INPUT_POST, 'purchase_requests', FILTER_DEFAULT , FILTER_REQUIRE_ARRAY);

    foreach($ids as $req_id) {
        try {
            plugin_movieviewer_validate_deal_pack_request_id($req_id);
        } catch (MovieViewerValidationException $ex) {
            return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
        }
    }

    $requests = array();
    foreach($ids as $req_id) {
        try {
            $request = plugin_movieviewer_get_deal_pack_purchase_request_repository()->findById($req_id);
        } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
            return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
        }
        $requests[] = $request;
    }

    foreach($requests as $request) {
        $request->confirmPayment();
    }

    $hsc = "plugin_movieviewer_hsc";

    foreach($requests as $request) {
        $confirmation = $request->getPaymentConfirmation();

        $content_row =<<<TEXT
        <tr>
          <td>{$hsc($request->getUser()->lastName)} {$hsc($request->getUser()->firstName)}</td>
          <td>{$hsc($request->getUser()->id)}</td>
          <td>{$hsc($request->getPack()->describe())}</td>
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
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
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