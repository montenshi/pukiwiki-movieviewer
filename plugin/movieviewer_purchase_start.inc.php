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

    $action_uri = plugin_movieviewer_get_script_uri() . "cmd=movieviewer_purchase_start";
    $start_uri_credit = plugin_movieviewer_get_script_uri() . "?${pages[1]}&purchase_pack_id=1_2";

    $page = plugin_movieviewer_get_current_page();

    $settings = plugin_movieviewer_get_global_settings();
    $offer_maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);

    if (!$offer_maker->canOffer()) {
        return plugin_movieviewer_convert_error_response("ご指定のコースはすでに申し込み済み、または、受講できなくなりました。");
    }

    $offer = $offer_maker->getOffer();

    if ($offer->getPackId() !== $deal_pack_id) {
        return plugin_movieviewer_convert_error_response("ご指定のコースはすでに申し込み済み、または、受講できなくなりました。");
    }

    $hsc = "plugin_movieviewer_hsc";
    $input_csrf_token = "plugin_movieviewer_generate_input_csrf_token";

    $bank_accounts_with_notes = nl2br($offer->getBankTransfer()->bank_accounts_with_notes);
    $price_with_notes = plugin_movieviewer_render_dealpack_offer_price($offer);

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
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
        <input type="hidden" name="page" value="{$hsc($page)}">
        <input type="hidden" name="deal_pack_id" value="{$hsc($deal_pack_id)}">
        <input type="hidden" name="purchase_method" value="bank">
        {$input_csrf_token()}
        <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">確認する</button>
    </form>
TEXT;

    return $content;
}

function plugin_movieviewer_purchase_start_action() {

    $page = plugin_movieviewer_get_current_page();

    try {
        plugin_movieviewer_validate_csrf_token();
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_action_error_response($page, "不正なリクエストです。");
    }

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_action_error_response($page, "ログインが必要です。");
    }

    if ($user->mailAddress === NULL || $user->mailAddress === "") {
        return plugin_movieviewer_action_error_response($page, "メールアドレスが登録されていません。");
    }

    $deal_pack_id = filter_input(INPUT_POST, "deal_pack_id");
    $purchase_method = filter_input(INPUT_POST, "purchase_method");

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

    $mail_builder = new MovieViewerDealPackBankTransferInformationMailBuilder($settings->mail);
    $mail = $mail_builder->build($user, $offer->getPackName(), $price_with_notes, $offer->getBankTransfer());
    $result = $mail->send();

    if (!$result) {
        MovieViewerLogger::getLogger()->addError(
            "案内通知エラー", array("error_statement"=>$mail->errorStatment())
        );

        return plugin_movieviewer_action_error_response($page, "メールの送信に失敗しました。{$settings->contact['name']}に問い合わせしてください。");
    }

    $hsc = "plugin_movieviewer_hsc";

    $back_uri = plugin_movieviewer_get_script_uri() . "?" . plugin_movieviewer_purchase_start_get_back_page();
    $bank_accounts_with_notes = nl2br($offer->getBankTransfer()->bank_accounts_with_notes);

    $content =<<<TEXT
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>受講申し込み完了</h2>
    <p>
    ご登録のアドレスに振込先等のご案内をお送りしています。<br>
    ご確認の上、お振込を期限までに完了してください。<br>
    現在の状況を会員ページに戻って、ご確認ください。
    </p>
    <p>
    <a href="{$back_uri}">会員ページに戻る</a>
    </p>
TEXT;

    return array("msg"=>$page, "body"=>$content);
}

?>