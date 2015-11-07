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

    $offer->accept();

    $mail_builder = new MovieViewerDealPackBankTransferInformationMailBuilder($settings->mail);
    $mail = $mail_builder->build($user, $offer->getPackName(), $offer->getPrice()->amount, $offer->getBankTransfer());
    $result = $mail->send();

    if (!$result) {
        MovieViewerLogger::getLogger()->addError(
            "案内通知エラー", array("error_statement"=>$mail->errorStatment())
        );

        return plugin_movieviewer_convert_error_response("メールの送信に失敗しました。{$settings->contact['name']}に問い合わせしてください。");
    }

    $hsc = "plugin_movieviewer_hsc";

    $back_uri = plugin_movieviewer_get_script_uri() . "?" . plugin_movieviewer_purchase_start_get_back_page();
    $bank_accounts_with_notes = nl2br($offer->getBankTransfer()->bank_accounts_with_notes);
    $notes =nl2br($offer->getBankTransfer()->notes);

    $content =<<<TEXT
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>受講申し込み完了</h2>
    <p>
    ご登録のアドレスにも同じ内容をメールでお送りしています。<br>
    ご確認の上、お振込を期限までに完了してください。<br>
    現在の状況を会員ページに戻って、ご確認ください。
    </p>
    <p>
    <table class="movieviewer-purchase-request-details">
      <tr><th>項目</th><td>{$hsc($offer->describePack())}</td></tr>
      <tr><th>金額</th><td>{$hsc(number_format($offer->getPrice()->amount))}円</td></tr>
      <tr><th>振込先</th><td>{$bank_accounts_with_notes}</td></tr>
      <tr><th>振込期限</th><td>{$hsc($offer->getBankTransfer()->deadline->format("Y年m月d日"))}まで</td></tr>
    </table>
    </p>
    <p>
    <a href="{$back_uri}">会員ページに戻る</a>
    </p>
TEXT;

    return $content;
}

?>