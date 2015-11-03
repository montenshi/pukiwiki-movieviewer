<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_purchase_confirm_payment_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_purchase_confirm_payment_convert() {

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">管理者でログインする必要があります。</p>
TEXT;
        return $content;
    }

    if (!$user->isAdmin()) {
      $content =<<<TEXT
      <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
      <p class="caution">管理者でログインする必要があります。</p>
TEXT;
      return $content;
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

    $content_rows_notified = "";
    $content_rows_unnotified = "";

    foreach($requestsNotConfirmed as $request) {

        $ctrl_value = "{$request->getId()}";
        $ctrl_id = "pr_{$ctrl_value}";

        $content_row =<<<TEXT
        <tr>
          <td><input type="checkbox" name="purchase_requests[]" value="{$ctrl_value}" id="{$ctrl_id}"></td>
          <td><label for="{$ctrl_id}">{$request->getUser()->memberId}</label></td>
          <td><label for="{$ctrl_id}">{$request->getUser()->lastName} {$request->getUser()->firstName}</label></td>
          <td><label for="{$ctrl_id}">{$request->getUser()->id}</label></td>
          <td><label for="{$ctrl_id}">{$request->getPack()->describe()}</label></td>
          <td><label for="{$ctrl_id}">{$request->getDateRequested()->format("Y/m/d H:m:s")}</label></td>
        </tr>
TEXT;

        if ($request->isNotified()) {
            $content_rows_notified .= $content_row;
        } else {
            $content_rows_unnotified .= $content_row;
        }
    }

    $page = plugin_movieviewer_get_current_page();
    $action_url = get_script_uri() . "?cmd=movieviewer_purchase_confirm_payment&page=$page";

    $content_notified = "";
    if ($content_rows_notified !== "") {
        $content_notified =<<<TEXT
        <div>
            <h3>通知あり</h3>
            <table class="table purchase-requests purchase-requests-notified">
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
                {$content_rows_notified}
              <tbody>
            </table>
        </div>
TEXT;
    }

    $content_unnotified = "";
    if ($content_rows_unnotified !== "") {
        $content_unnotified =<<<TEXT
        <div>
            <h3>通知なし</h3>
            <table class="table purchase-requests purchase-requests-unnotified">
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
                {$content_rows_unnotified}
              <tbody>
            </table>
        </div>
TEXT;
    }

    $content =<<<TEXT
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>入金確認</h2>
    <p>
      入金が確認できたものにチェックを付けて、確認ボタンを押してください。
    </p>
    <p>
    <form action="{$action_url}" method="POST">
    <input type="hidden" name="ope_type" value="confirm">
    $content_notified
    $content_unnotified
    </p>
    <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">確認</button>
    </form>
TEXT;

    return $content;
}

function plugin_movieviewer_purchase_confirm_payment_action() {

    $page = plugin_movieviewer_get_current_page();

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return array('msg'=>$page, 'body'=>"ログインが必要です。");
    }

    if (!$user->isAdmin()) {
        return array('msg'=>$page, 'body'=>"管理者でログインする必要があります。");
    }

    $ope_type = $_POST['ope_type'];

    if ($ope_type === 'confirm') {
        return plugin_movieviewer_purchase_confirm_payment_action_confirm();
    }

    if ($ope_type === 'execute') {
        return plugin_movieviewer_purchase_confirm_payment_action_execute();
    }

    return array('msg'=>$page, 'body'=>"処理ができません。最初からやり直してください。");
}

function plugin_movieviewer_purchase_confirm_payment_action_confirm() {

    $ids = $_POST['purchase_requests'];

    $requests = array();
    foreach($ids as $req_id) {
        $request = plugin_movieviewer_get_deal_pack_purchase_request_repository()->findById($req_id);
        $requests[] = $request;
    }

    foreach($requests as $request) {

        $ctrl_value = "{$request->getId()}";
        $ctrl_id = "pr_{$ctrl_value}";

        if (!$request->isPaymentConfirmed()) {
            $confirmation = $request->preConfirmPayment();
        } else {
            $confirmation = $request->getPaymentConfirmation();
        }

        $content_row =<<<TEXT
        <tr>
          <input type="hidden" name="purchase_requests[]" value="{$ctrl_value}" id="{$ctrl_id}">
          <td>{$request->getUser()->lastName} {$request->getUser()->firstName}</td>
          <td>{$request->getUser()->id}</td>
          <td>{$request->getPack()->describe()}</td>
          <td>{$request->getDateRequested()->format("Y/m/d H:m:s")}</td>
          <td>{$confirmation->getViewingPeriod()->date_begin->format("Y/m/d")}</td>
          <td>{$confirmation->getViewingPeriod()->date_end->format("Y/m/d")}</td>
        </tr>
TEXT;
        $content_rows .= $content_row;
    }

    $action_url = get_script_uri() . "?cmd=movieviewer_purchase_confirm_payment";

    $content =<<<TEXT
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>入金確認(最終確認)</h2>
    <p>
      以下の申し込みの入金を確定します。確認の上、確定ボタンを押してください。
    </p>
    <p>
    <form action="{$action_url}" method="POST">
    <input type="hidden" name="ope_type" value="execute">
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

    $ids = $_POST['purchase_requests'];

    $requests = array();
    foreach($ids as $req_id) {
        $request = plugin_movieviewer_get_deal_pack_purchase_request_repository()->findById($req_id);
        $requests[] = $request;
    }

    foreach($requests as $request) {
        $request->confirmPayment();
    }

    foreach($requests as $request) {
        $ctrl_value = "{$request->getId()}";
        $ctrl_id = "pr_{$ctrl_value}";

        $confirmation = $request->getPaymentConfirmation();

        $content_row =<<<TEXT
        <tr>
          <td>{$request->getUser()->lastName} {$request->getUser()->firstName}</td>
          <td>{$request->getUser()->id}</td>
          <td>{$request->getPack()->describe()}</td>
          <td>{$request->getDateRequested()->format("Y/m/d H:m:s")}</td>
          <td>{$confirmation->getViewingPeriod()->date_begin->format("Y/m/d")}</td>
          <td>{$confirmation->getViewingPeriod()->date_end->format("Y/m/d")}</td>
        </tr>
TEXT;
        $content_rows .= $content_row;
    }

    $content =<<<TEXT
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