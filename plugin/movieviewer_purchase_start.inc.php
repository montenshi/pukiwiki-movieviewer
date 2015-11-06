<?php

require_once("movieviewer.ini.php");

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

    $action_uri = get_script_uri() . "cmd=movieviewer_purchase_start";
    $start_uri_credit = get_script_uri() . "?${pages[1]}&purchase_pack_id=1_2";

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

    $bank_account = nl2br($offer->getBankTransfer()->bank_account);

    $hsc = "plugin_movieviewer_hsc";
    $input_csrf_token = "plugin_movieviewer_generate_input_csrf_token";

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>銀行振り込みで申し込み</h2>
    <p>
    申し込み内容を確認してください。<br>
    「申し込みする」ボタンをクリックすると、
    登録しているメールアドレスに銀行振り込み手続きのご案内メールが届きます。
    </p>
    <p>
    <table class="movieviewer-purchase-request-details">
      <tr><th>項目</th><td>{$hsc($offer->describePack())}</td></tr>
      <tr><th>金額</th><td>{$hsc(number_format($offer->getPrice()->amount))}円</td></tr>
      <tr><th>振込先</th><td>{$bank_account}</td></tr>
      <tr><th>振込期限</th><td>{$hsc($offer->getBankTransfer()->deadline->format("Y年m月d日"))}まで</td></tr>
    </table>
    </p>
    <form action="index.php?cmd=movieviewer_purchase_start" METHOD="POST">
        <input type="hidden" name="ope_type" value="request">
        <input type="hidden" name="page" value="{$hsc($page)}">
        <input type="hidden" name="deal_pack_id" value="{$hsc($deal_pack_id)}">
        <input type="hidden" name="purchase_method" value="bank">
        {$input_csrf_token()}
        <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">申し込みする</button>
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

    $mail_builder = new MovieViewerDealPackBankTransferInformationMailBuilder($settings->mail);
    $mail = $mail_builder->build($user, $offer->getPackName(), $offer->getPrice()->amount, $offer->getBankTransfer());
    $result = $mail->send();

    if (!$result) {
        MovieViewerLogger::getLogger()->addError(
            "案内通知エラー", array("error_statement"=>$mail->errorStatment())
        );

        print_r($settings->mail);

        return plugin_movieviewer_action_error_response($page, "メールの送信に失敗しました。スタッフに問い合わせしてください。");
    }

    $bank_account = nl2br($offer->getBankTransfer()->bank_account);

    $hsc = "plugin_movieviewer_hsc";

    $content =<<<TEXT
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>銀行振り込みで申し込み(案内通知)</h2>
    <p>
    登録しているメールアドレスに、申し込み案内を送りました。
    </p>
    <p>
    <table class="movieviewer-purchase-request-details">
      <tr><th>項目</th><td>{$hsc($offer->describePack())}</td></tr>
      <tr><th>金額</th><td>{$hsc(number_format($offer->getPrice()->amount))}円</td></tr>
      <tr><th>振込先</th><td>{$bank_account}</td></tr>
      <tr><th>振込期限</th><td>{$hsc($offer->getBankTransfer()->deadline->format("Y年m月d日"))}まで</td></tr>
    </table>
    </p>
TEXT;

    return array("msg"=>$page, "body"=>$content);

}
?>