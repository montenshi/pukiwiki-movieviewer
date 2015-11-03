<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_purchase_notify_payment_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_purchase_notify_payment_action() {

    $page = ”入金完了通知”;

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">ログインが必要です。</p>
TEXT;
        return array("msg"=>$page, "body"=>$content);
    }

    $deal_pack_id = $_GET["deal_pack_id"];

    $repo = plugin_movieviewer_get_deal_pack_purchase_request_repository();

    try {
        $request = $repo->findBy($user->id, $deal_pack_id);
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">ログインが必要です。</p>
TEXT;
        return array("msg"=>$page, "body"=>$content);
    }

    if ($request->isPaymentConfirmed()) {
        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">ご指定のコースは入金確認済みです。</p>
TEXT;
        return array("msg"=>$page, "body"=>$content);
    }

    $settings = plugin_movieviewer_get_global_settings();
    $mail_builder = new MovieViewerDealPackNotifyPaymentMailBuilder($settings->mail);
    $mail = $mail_builder->build($user, $request->getPack());
    $result = $mail->send();

    if (!$result) {
        MovieViewerLogger::getLogger()->addError(
            "入金完了通知エラー", array("error_statement"=>$mail->errorStatment())
        );

        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">メールの送信に失敗しました。スタッフに問い合わせしてください。</p>
TEXT;
        return array("msg"=>$page, "body"=>$content);
    }

    $request->notifyPayment();

    try {
        $repo->store($request);
    } catch (MovieViewerRepositoryObjectCantStoreException $ex) {
        MovieViewerLogger::getLogger()->addError(
            "入金完了通知保存エラー", array("exeption", $ex)
        );

        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">処理に失敗しました。スタッフに問い合わせしてください。</p>
TEXT;
        return array("msg"=>$page, "body"=>$content);
    }

    $content =<<<TEXT
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>入金完了通知</h2>
    <p>
    スタッフに入金完了のメールを送りました。<br>
    項目: {$request->getPack()->describe()}
    </p>
TEXT;

    return array("msg"=>$page, "body"=>$content);
}

?>