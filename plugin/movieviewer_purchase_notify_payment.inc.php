<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_purchase_notify_payment_set_back_page($page) {
    $_SESSION['movieviewer_puarchase_notify_payment_back_page'] = $page;
}

function plugin_movieviewer_purchase_notify_payment_get_back_page() {
    return $_SESSION['movieviewer_puarchase_notify_payment_back_page'];
}

function plugin_movieviewer_purchase_notify_payment_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_purchase_notify_payment_action() {

    $page = ”入金完了通知”;

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_action_error_response($page, "ログインが必要です。");
    }

    $deal_pack_id = filter_input(INPUT_GET, "deal_pack_id");

    try {
        plugin_movieviewer_validate_deal_pack_id($deal_pack_id);
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
    }

    $repo = plugin_movieviewer_get_deal_pack_purchase_request_repository();

    try {
        $request = $repo->findBy($user->id, $deal_pack_id);
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return plugin_movieviewer_action_error_response($page, "指定した内容に誤りがあります。");
    }

    if ($request->isPaymentConfirmed()) {
        return plugin_movieviewer_action_error_response($page, "ご指定のコースは入金確認済みです。");
    }

    $settings = plugin_movieviewer_get_global_settings();
    $mail_builder = new MovieViewerDealPackNotifyPaymentMailBuilder($settings->mail);
    $mail = $mail_builder->build($user, $request->getPack());
    $result = $mail->send();

    if (!$result) {
        MovieViewerLogger::getLogger()->addError(
            "入金完了通知エラー", array("error_statement"=>$mail->errorStatment())
        );

        return plugin_movieviewer_action_error_response($page, "メールの送信に失敗しました。スタッフに問い合わせしてください。");
    }

    $request->notifyPayment();

    try {
        $repo->store($request);
    } catch (MovieViewerRepositoryObjectCantStoreException $ex) {
        MovieViewerLogger::getLogger()->addError(
            "入金完了通知保存エラー", array("exeption", $ex)
        );

        return plugin_movieviewer_action_error_response($page, "処理に失敗しました。スタッフに問い合わせしてください。");
    }

    $hsc = "plugin_movieviewer_hsc";

    $back_uri = plugin_movieviewer_get_script_uri() . "?" . plugin_movieviewer_purchase_notify_payment_get_back_page();

    $content =<<<TEXT
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>入金完了通知</h2>
    <p>
    入金完了をエンジェルズハウス研究所(AHL)に通知しました。<br>
    現在の状況を会員ページに戻って、ご確認ください。<br>
    </p>
    <p>
    受講セット: {$hsc($request->getPack()->describe())}
    </p>
    <p>
    <a href="{$back_uri}">会員ページに戻る</a>
    </p>
TEXT;

    return array("msg"=>$page, "body"=>$content);
}

?>