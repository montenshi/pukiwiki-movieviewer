<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_review_purchase_start_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_review_purchase_start_convert(){

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_convert_error_response("ログインが必要です。");
    }
    
    $purchase_method = filter_input(INPUT_GET, "purchase_method");
    $items_value = filter_input(INPUT_GET, "items");

    try {
        plugin_movieviewer_validate_purchase_method($purchase_method);
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_convert_error_response("指定した内容に誤りがあります。");
    }

    if ($purchase_method === "credit") {
        // 取引IDに会員番号を利用するため、会員番号がない場合は、クレジットカード支払いはできない
        if (!$user->hasMemberId()) {
            return plugin_movieviewer_convert_error_response("クレジットカードの支払いには会員番号が必要です。");
        }
    }

    $settings = plugin_movieviewer_get_global_settings();

    $request = null;
    try {
        $items = split(',', $items_value);
        $request = new MovieViewerReviewPackPurchaseRequest($user->id, $purchase_method, $items);
    } catch (InvalidArgumentException $ex) {
        return plugin_movieviewer_convert_error_response("指定した内容に誤りがあります。");
    }

    try {
        $repo = plugin_movieviewer_get_review_pack_purchase_request_repository();
        $request_stash_id = $repo->stash($request);
    } catch (Exception $ex) {
        return plugin_movieviewer_convert_error_response("処理に失敗しました");
    }

    $page = plugin_movieviewer_get_current_page();

    if ($purchase_method === "bank") {
        $content_body = plugin_movieviewer_review_purchase_start_convert_bank($settings, $user, $request, $request_stash_id, $page);
    } else if ($purchase_method === "credit") {
        $content_body = plugin_movieviewer_review_purchase_start_convert_credit($settings, $user, $request, $request_stash_id, $page);
    }

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    $content_body
TEXT;

    return $content;
}

function plugin_movieviewer_review_purchase_start_convert_bank($settings, $user, $request, $request_stash_id, $current_page) {

    $hsc = "plugin_movieviewer_hsc";
    $input_csrf_token = "plugin_movieviewer_generate_input_csrf_token";

    $request_details = plugin_movieviewer_review_purchase_start_convert_get_request_details($settings, $request);

    $content =<<<TEXT
    <h2>再視聴申し込み</h2>
    <p>
    申し込み内容を確認してください。<br>
    「申し込み」ボタンをクリックして、申し込みを完了して下さい。<br>
    ご登録されているアドレスに振込先等のご案内をお送りします。
    </p>
    {$request_details}
    <form action="index.php?cmd=movieviewer_review_purchase_start" METHOD="POST">
        <input type="hidden" name="page" value="{$hsc($current_page)}">
        <input type="hidden" name="request_stash_id" value="{$request_stash_id}">
        {$input_csrf_token()}
        <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">申し込み</button>
    </form>
TEXT;

    return $content;
}

function plugin_movieviewer_review_purchase_start_convert_credit($settings, $user, $request, $request_stash_id, $current_page) {

    // 取引IDに会員番号を利用するため、会員番号がない場合は、クレジットカード支払いはできない
    if (!$user->hasMemberId()) {
        return plugin_movieviewer_convert_error_response("クレジットカードの支払いには会員番号が必要です。");
    }

    $paygent_settings = $settings->payment->credit->paygent;
    $generator = new MovieViewerReviewPackPaygentParameterGenerator($paygent_settings, $user, $request);

    $return_params = array(
          "cmd" => "movieviewer_review_purchase_start"
        , "request_stash_id" => $request_stash_id
    );
    $return_uri = plugin_movieviewer_get_script_uri() . "?" . http_build_query($return_params);

    $hsc = "plugin_movieviewer_hsc";

    $request_details = plugin_movieviewer_review_purchase_start_convert_get_request_details($settings, $request);

    $content =<<<TEXT
    <h2>再視聴申し込み</h2>
    <p>
    申し込み内容を確認してください。<br>
    「申し込む」ボタンをクリックすると、クレジットカードの支払いページに移動します。<br>
    支払いページは、提携の決済代行会社ペイジェントのページになります。<br>
    </p>
    <p>
    決済の手続きが終了すると当サイトに再び戻ってきますので、それまで手続きを続けてください。
    当サイトに戻ってくる前に手続きを中断してしまった場合、購入完了となりませんのでご注意ください。<br>
    </p>
    <p>
    ※ 当研究所では、会員のみなさまのクレジットカード情報は一切お預かりいたしません。<br>
    </p>
    {$request_details}

    ※　クレジット決済画面の表示後、５日以内に入金を完了させてください。<br>
    期限が過ぎると、クレジット決済画面が表示されなくなり、入金できなくなります。
    </p>
    <form action="{$paygent_settings["request_uri"]}" method="post">
        <input type="hidden" name="trading_id" value="{$generator->getTradingId()}">
        <input type="hidden" name="id" value="{$generator->getId()}">
        <input type="hidden" name="seq_merchant_id" value="{$generator->getSeqMerchantId()}">
        <input type="hidden" name="hc" value="{$generator->getHash()}">
        <input type="hidden" name="payment_detail" value="{$generator->getPaymentDetail()}">
        <input type="hidden" name="return_url" value="$return_uri">
        <button type="submit" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>申し込む</button>
    </form>
TEXT;

    return $content;
}

function plugin_movieviewer_review_purchase_start_convert_get_request_details($settings, $request) {

    $courses = plugin_movieviewer_get_courses_repository()->find();
    $itemsByCourse = $request->getItemsByCourse();

    $item_description = "";
    foreach($itemsByCourse as $course_id => $items) {
        $course = $courses->getCourse($course_id);
        $session_list = "";
        foreach($items as $item) {
            $session = $course->getSession($item->session_id);
            $session_list .= "<li>{$session->describe()}</li>";
        }
        $item_description .=<<<TEXT
        {$course->describe()}
        <ul style='margin-left:0;'>
            {$session_list}
        </ul>
TEXT;
    }

    $payment_guide = MovieViewerReviewPackPurchasePaymentGuide::create($settings->payment, $request);

    $price_with_notes = plugin_movieviewer_render_price_with_notes($request->getPrice(), "回", FALSE);
    $bank_accounts_with_notes = nl2br($payment_guide->bank_transfer->bank_accounts_with_notes);

    $hsc = "plugin_movieviewer_hsc";

    $content =<<<TEXT
    <p>
    <table class="movieviewer-purchase-request-details">
      <tr><th>再視聴</th><td>{$item_description}</td></tr>
      <tr><th>金額</th><td>{$price_with_notes}</td></tr>
      <tr><th>振込先</th><td>{$bank_accounts_with_notes}</td></tr>
      <tr><th>振込期限</th><td>{$hsc($payment_guide->deadline->format("Y年m月d日"))}まで</td></tr>
    </table>
    </p>
TEXT;

    return $content;
}

function plugin_movieviewer_review_purchase_start_action(){

    $from_external_link = FALSE;
    $test_var = filter_input(INPUT_POST, "request_stash_id");
    if (empty($test_var)) {
        $from_external_link = TRUE;
    }

    if ($from_external_link) {
        $request_stash_id = filter_input(INPUT_GET, "request_stash_id");
    } else {
        $request_stash_id = filter_input(INPUT_POST, "request_stash_id");
    }

    if (!$from_external_link) {
        try {
            plugin_movieviewer_validate_csrf_token();
        } catch (MovieViewerValidationException $ex) {
            return plugin_movieviewer_action_error_response($page, "不正なリクエストです。");
        }
    }

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_action_error_response($page, "ログインが必要です。");
    }
    
    if ($user->mailAddress === NULL || $user->mailAddress === "") {
        return plugin_movieviewer_action_error_response($page, "メールアドレスが登録されていません。");
    }
    
    $settings = plugin_movieviewer_get_global_settings();
    $service = new MovieViewerReviewPackPurchaseRequestService($settings);

    $request = null;
    try {
        $request = $service->doRequest($user, $request_stash_id);
    } catch (Exception $ex) {
        return plugin_movieviewer_action_error_response($page, $ex->getMessage());
    }

    if ($request->purchase_method === "bank") {
        $messages = plugin_movieviewer_review_purchase_start_action_bank($settings, $user, $request);
    } else if ($request->purchase_method === "credit") {
        $messages = plugin_movieviewer_review_purchase_start_action_credit($settings, $user, $request);
    }

    $page = plugin_movieviewer_get_current_page();
    $back_uri = plugin_movieviewer_get_home_uri();

    $content =<<<TEXT
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    <h2>再視聴申し込み完了</h2>
    <p>
    $messages
    </p>
    <p>
    <a href="{$back_uri}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>マイページに戻る</a>
    </p>
TEXT;

    return array("msg"=>$page, "body"=>$content);
}

function plugin_movieviewer_review_purchase_start_action_bank($settings, $user, $request) {
    $messages =<<<TEXT
    ご登録のアドレスに振込先等のご案内をお送りしています。<br>
    ご確認の上、お振込を期限までに完了してください。<br>
    現在の状況をマイページに戻って、ご確認ください。
TEXT;

    return $messages;
}

function plugin_movieviewer_review_purchase_start_action_credit($settings, $user, $request) {
    $messages =<<<TEXT
    クレジットカードでの支払いが完了しました。<br>
    現在の状況をマイページに戻って、ご確認ください。<br>
    なおシステムの関係上、入金の確認には、しばらくお時間がかかることがありますので、ご了承ください。
TEXT;

    return $messages;
}

?>