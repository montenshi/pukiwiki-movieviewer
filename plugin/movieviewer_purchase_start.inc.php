<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_purchase_start_set_back_page($page) {
    $_SESSION['movieviewer_puarchase_start_back_page'] = $page;
}

function plugin_movieviewer_purchase_start_get_back_page() {
    return $_SESSION['movieviewer_puarchase_start_back_page'];
}

function plugin_movieviewer_purchase_start_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_purchase_start_convert() {

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_convert_error_response("ログインが必要です。");
    }
    
    // 取引IDに会員番号を利用するため、会員番号がない場合は、クレジットカード支払いはできない
    if (!$user->hasMemberId()) {
        return plugin_movieviewer_convert_error_response("クレジットカードの支払いには会員番号が必要です。");
    }

    $deal_pack_id = filter_input(INPUT_GET, "deal_pack_id");
    $purchase_method = filter_input(INPUT_GET, "purchase_method");

    try {
        plugin_movieviewer_validate_deal_pack_id($deal_pack_id);
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_convert_error_response("指定した内容に誤りがあります。");
    }

    try {
        plugin_movieviewer_validate_purchase_method($purchase_method);
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_convert_error_response("指定した内容に誤りがあります。");
    }

    $settings = plugin_movieviewer_get_global_settings();
    $offer_maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);

    if (!$offer_maker->canOffer()) {
        return plugin_movieviewer_convert_error_response("ご指定のコースはすでに申し込み済み、または、受講できなくなりました。");
    }

    $offer = $offer_maker->getOffer();

    if ($offer->getPackId() !== $deal_pack_id) {
        return plugin_movieviewer_convert_error_response("ご指定のコースはすでに申し込み済み、または、受講できなくなりました。");
    }

    $page = plugin_movieviewer_get_current_page();

    if ($purchase_method === "bank") {
        $content_body = plugin_movieviewer_purchase_start_convert_bank($settings, $user, $offer, $page);
    } else if ($purchase_method === "credit") {
        $content_body = plugin_movieviewer_purchase_start_convert_credit($settings, $user, $offer, $page);
    }

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    $content_body
TEXT;

    return $content;
}

function plugin_movieviewer_purchase_start_convert_bank($settings, $user, $offer, $current_page) {

    $hsc = "plugin_movieviewer_hsc";
    $input_csrf_token = "plugin_movieviewer_generate_input_csrf_token";

    $price_with_notes = plugin_movieviewer_render_dealpack_offer_price($offer);
    $bank_accounts_with_notes = nl2br($offer->getBankTransfer()->bank_accounts_with_notes);

    $content =<<<TEXT
    <h2>受講申し込み</h2>
    <p>
    申し込み内容を確認してください。<br>
    「確認する」ボタンをクリックすると、
    ご登録されているアドレスに振込先等のご案内をお送りします。
    </p>
    <p>
    <table class="movieviewer-purchase-request-details">
      <tr><th>項目</th><td>{$hsc($offer->describePack())}</td></tr>
      <tr><th>金額</th><td>{$price_with_notes}</td></tr>
      <tr><th>振込先</th><td>{$bank_accounts_with_notes}</td></tr>
      <tr><th>振込期限</th><td>{$hsc($offer->getBankTransfer()->deadline->format("Y年m月d日"))}まで</td></tr>
    </table>
    </p>
    <form action="index.php?cmd=movieviewer_purchase_start" METHOD="POST">
        <input type="hidden" name="page" value="{$hsc($current_page)}">
        <input type="hidden" name="deal_pack_id" value="{$hsc($offer->getPackId())}">
        <input type="hidden" name="purchase_method" value="bank">
        {$input_csrf_token()}
        <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">確認する</button>
    </form>
TEXT;

    return $content;
}

function plugin_movieviewer_purchase_start_convert_credit($settings, $user, $offer, $current_page) {

    // 取引IDに会員番号を利用するため、会員番号がない場合は、クレジットカード支払いはできない
    if (!$user->hasMemberId()) {
        return plugin_movieviewer_convert_error_response("クレジットカードの支払いには会員番号が必要です。");
    }
    
    $paygent_settings = $settings->payment->credit->paygent;
    $generator = new MovieViewerDealPackPaygentParameterGenerator($paygent_settings, $user, $offer);

    $return_params = array(
          "cmd" => "movieviewer_purchase_start"
        , "purchase_method" => "credit"
        , "deal_pack_id" => $offer->getPackId()
        //, "page" => $current_page
    );
    $return_uri = plugin_movieviewer_get_script_uri() . "?" . http_build_query($return_params);

    $price_with_notes = plugin_movieviewer_render_dealpack_offer_price($offer);

    $hsc = "plugin_movieviewer_hsc";

    $content =<<<TEXT
    <h2>受講申し込み</h2>
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
    <p>
    <table class="movieviewer-purchase-request-details">
      <tr><th>項目</th><td>{$hsc($offer->describePack())}</td></tr>
      <tr><th>金額</th><td>{$price_with_notes}</td></tr>
    </table>
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

function plugin_movieviewer_purchase_start_action() {

    $page = plugin_movieviewer_get_current_page();

    $from_external_link = FALSE;
    $test_var = filter_input(INPUT_POST, "purchase_method");
    if (empty($test_var)) {
        $from_external_link = TRUE;
    }

    if ($from_external_link) {
        $deal_pack_id = filter_input(INPUT_GET, "deal_pack_id");
        $purchase_method = filter_input(INPUT_GET, "purchase_method");
    } else {
        $deal_pack_id = filter_input(INPUT_POST, "deal_pack_id");
        $purchase_method = filter_input(INPUT_POST, "purchase_method");
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
    
    try {
        plugin_movieviewer_validate_deal_pack_id($deal_pack_id);
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
    }

    try {
        plugin_movieviewer_validate_purchase_method($purchase_method);
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
    }

    $settings = plugin_movieviewer_get_global_settings();
    $offer_maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);

    if (!$offer_maker->canOffer()) {
        return plugin_movieviewer_action_error_response($page, "ご指定のコースはすでに申し込み済み、または、受講できなくなりました。");
    }

    $offer = $offer_maker->getOffer();

    if ($offer->getPackId() !== $deal_pack_id) {
        return plugin_movieviewer_action_error_response($page, "ご指定のコースはすでに申し込み済み、または、受講できなくなりました。");
    }

    $offer->accept();

    $price_with_notes = plugin_movieviewer_render_dealpack_offer_price($offer, TRUE);

    $back_uri = plugin_movieviewer_get_script_uri() . "?" . plugin_movieviewer_purchase_start_get_back_page();
    $bank_accounts_with_notes = nl2br($offer->getBankTransfer()->bank_accounts_with_notes);

    if ($purchase_method === "bank") {
        $mail_builder = new MovieViewerDealPackBankTransferInformationMailBuilder($settings->mail);
        $mail = $mail_builder->build($user, $offer->getPackName(), $price_with_notes, $offer->getBankTransfer());
        $result = $mail->send();

        if (!$result) {
            MovieViewerLogger::getLogger()->addError(
                "案内通知エラー", array("error_statement"=>$mail->errorStatment())
            );

            return plugin_movieviewer_action_error_response($page, "メールの送信に失敗しました。{$settings->contact['name']}に問い合わせしてください。");
        }
        
        $messages =<<<TEXT
        ご登録のアドレスに振込先等のご案内をお送りしています。<br>
        ご確認の上、お振込を期限までに完了してください。<br>
        現在の状況をマイページに戻って、ご確認ください。
TEXT;
    } else if ($purchase_method === "credit") {
        $messages =<<<TEXT
        クレジットカードでの支払いが完了しました。<br>
        現在の状況をマイページに戻って、ご確認ください。<br>
        なおシステムの関係上、入金の確認には、しばらくお時間がかかることがありますので、ご了承ください。
TEXT;
    }

    $hsc = "plugin_movieviewer_hsc";

    $content =<<<TEXT
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>受講申し込み完了</h2>
    <p>
    $messages
    </p>
    <p>
    <a href="{$back_uri}">マイページに戻る</a>
    </p>
TEXT;

    return array("msg"=>$page, "body"=>$content);
}

?>