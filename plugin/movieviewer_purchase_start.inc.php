<?php

/**
 * Pukiwikiプラグイン::動画視聴 受講申し込み
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewerPlugin
 * @package  DealPackPurchase
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

require_once "movieviewer.ini.php";

/**
 * プラグイン規定関数::初期化処理
 *
 * @return void
 */
function plugin_movieviewer_purchase_start_init()
{
    plugin_movieviewer_set_global_settings();
}

/**
 * プラグイン規定関数::ブロック型で呼び出された場合の処理
 * 認証済みの場合: 申し込み画面を生成する
 * 未認証の場合: エラー画面を生成する
 *
 * 引数: なし
 *
 * @return string 画面(html)
 */
function plugin_movieviewer_purchase_start_convert()
{

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

    // 取引IDに会員番号を利用するため、会員番号がない場合は、クレジットカード支払いはできない
    if ($purchase_method === "credit" && !$user->hasMemberId()) {
        return plugin_movieviewer_convert_error_response("クレジットカードの支払いには会員番号が必要です。");
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
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    $content_body
TEXT;

    return $content;
}

/**
 * プラグイン規定関数::アクション型で呼び出された場合の処理
 * 申し込みを確定させる
 * 
 * 引数: string purchase_method 支払い区分(bank, credit)
 *      string deal_pack_id 受講パックID
 * 
 * 注意: 単独で呼び出さないこと(convertの画面と連携している)
 *
 * @return array ページ名、画面(html)
 */
function plugin_movieviewer_purchase_start_action()
{

    $page = plugin_movieviewer_get_current_page();

    $from_external_link = false;
    $test_var = filter_input(INPUT_POST, "purchase_method");
    if (empty($test_var)) {
        $from_external_link = true;
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

    if ($user->mailAddress === null || $user->mailAddress === "") {
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

    if ($purchase_method === "bank") {
        $price_with_notes = plugin_movieviewer_render_dealpack_offer_price($offer, true);
        $mail_builder = new MovieViewerDealPackBankTransferInformationMailBuilder($settings->mail);
        $mail = $mail_builder->build($user, $offer->getPackName(), $price_with_notes, $offer->getPaymentGuide()->bank_transfer, $offer->getPaymentGuide()->deadline);
        $result = $mail->send();

        if (!$result) {
            MovieViewerLogger::getLogger()->addError(
                "案内通知エラー", array("error_statement"=>$mail->ErrorInfo)
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
    $back_uri = plugin_movieviewer_get_home_uri();

    $content =<<<TEXT
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    <h2>受講申し込み完了</h2>
    <p>
    $messages
    </p>
    <p>
    <a href="{$back_uri}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>マイページに戻る</a>
    </p>
TEXT;

    return array("msg"=>$page, "body"=>$content);
}

/*-- 以下、内部処理 --*/

/**
 * [ブロック] 銀行振込用の申し込みフォームを生成する
 *
 * @param MovieViewerSettings      $settings     プラグイン用設定
 * @param MovieViewerUser          $user         ログインユーザ
 * @param MovieViewerDealPackOffer $offer        受講申し込み購入オファー
 * @param string                   $current_page 画面名
 *
 * @return string 申し込みフォーム(html)
 */
function plugin_movieviewer_purchase_start_convert_bank($settings, $user, $offer, $current_page)
{

    $hsc = "plugin_movieviewer_hsc";
    $input_csrf_token = "plugin_movieviewer_generate_input_csrf_token";

    $price_with_notes = plugin_movieviewer_render_dealpack_offer_price($offer);
    $bank_accounts_with_notes = nl2br($offer->getPaymentGuide()->bank_transfer->bank_accounts_with_notes);

    $content =<<<TEXT
    <h2>受講申し込み</h2>
    <p>
    申し込み内容を確認してください。<br>
    「申し込み」ボタンをクリックして、申し込みを完了して下さい。<br>
    ご登録されているアドレスに振込先等のご案内をお送りします。
    </p>
    <p>
    <table class="movieviewer-purchase-request-details">
      <tr><th>項目</th><td>{$hsc($offer->describePack())}</td></tr>
      <tr><th>金額</th><td>{$price_with_notes}</td></tr>
      <tr><th>振込先</th><td>{$bank_accounts_with_notes}</td></tr>
      <tr><th>振込期限</th><td>{$hsc($offer->getPaymentGuide()->deadline->format("Y年m月d日"))}まで</td></tr>
    </table>
    </p>
    <form action="index.php?cmd=movieviewer_purchase_start" METHOD="POST">
        <input type="hidden" name="page" value="{$hsc($current_page)}">
        <input type="hidden" name="deal_pack_id" value="{$hsc($offer->getPackId())}">
        <input type="hidden" name="purchase_method" value="bank">
        {$input_csrf_token()}
        <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">申し込み</button>
    </form>
TEXT;

    return $content;
}

/**
 * [ブロック] クレジット支払い用の申し込みフォームを生成する
 *
 * @param MovieViewerSettings      $settings     プラグイン用設定
 * @param MovieViewerUser          $user         ログインユーザ
 * @param MovieViewerDealPackOffer $offer        受講申し込み購入オファー
 * @param string                   $current_page 画面名
 *
 * @return string 申し込みフォーム(html)
 */
function plugin_movieviewer_purchase_start_convert_credit($settings, $user, $offer, $current_page)
{

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
    <p>

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

?>