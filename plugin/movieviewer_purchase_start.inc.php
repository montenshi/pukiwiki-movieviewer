<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_purchase_start_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_purchase_start_convert() {

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return 'notfound';
    }

    $deal_pack_id = $_GET["deal_pack_id"];
    $purchase_method = $_GET["purchase_method"];

    $action_uri = get_script_uri() . "cmd=movieviewer_purchase_start";
    $start_uri_credit = get_script_uri() . "?${pages[1]}&purchase_pack_id=1_2";

    $page = plugin_movieviewer_get_current_page();

    $offer_maker = new MovieViewerDealPackOfferMaker($user);

    if (!$offer_maker->canOffer()) {
        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">ご指定のコースはすでに申し込み済み、または、購入できなくなりました。</p>
TEXT;
        return $content;
    }

    $offer = $offer_maker->getOffer();

    if ($offer->getPackId() !== $deal_pack_id) {
        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">ご指定のコースはすでに申し込み済み、または、購入できなくなりました。</p>
TEXT;
        return $content;
    }

    $hsc = "htmlspecialchars";

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
    <table>
      <tr><th>項目</th><td>{$offer->describePack()}</td></tr>
      <tr><th>金額</th><td>{$offer->getPrice()->amount}円</td></tr>
      <tr><th>振込先</th><td>ほげふが銀行 なんとか支店 (普) 12345678</td></tr>
      <tr><th>振込期限</th><td>{$offer->getTransferDeadline()->format("Y年m月d日")}まで</td></tr>
    </table>
    </p>
    <form action="index.php?cmd=movieviewer_purchase_start" METHOD="POST">
        <input type="hidden" name="ope_type" value="request">
        <input type="hidden" name="page" value="{$hsc($page)}">
        <input type="hidden" name="deal_pack_id" value="{$hsc($deal_pack_id)}">
        <input type="hidden" name="purchase_method" value="bank">
        <button type="submit" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">申し込みする</button>
    </form>
TEXT;

    return $content;
}

function plugin_movieviewer_purchase_start_action() {

    $page = plugin_movieviewer_get_current_page();

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">ログインが必要です。</p>
TEXT;
        return array("msg"=>$page, "body"=>$content);
    }

    $deal_pack_id = $_POST["deal_pack_id"];
    $purchase_method = $_POST["purchase_method"];

    $page = plugin_movieviewer_get_current_page();

    $offer_maker = new MovieViewerDealPackOfferMaker($user);

    if (!$offer_maker->canOffer()) {
        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">ご指定のコースは購入できなくなりました。</p>
TEXT;
        return array("msg"=>$page, "body"=>$content);
    }

    $offer = $offer_maker->getOffer();

    if ($offer->getPackId() !== $deal_pack_id) {
        $content =<<<TEXT
        <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
        <p class="caution">ご指定のコースは購入できなくなりました。</p>
TEXT;
        return array("msg"=>$page, "body"=>$content);
    }

    $offer->accept();

    $content =<<<TEXT
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>銀行振り込みで申し込み(案内通知)</h2>
    <p>
    申し込み案内を登録しているメールアドレスに送りました。
    </p>
    <p>
    <table>
      <tr><th>項目</th><td>{$offer->describePack()}</td></tr>
      <tr><th>金額</th><td>{$offer->getPrice()->amount}円</td></tr>
      <tr><th>振込先</th><td>ほげふが銀行 なんとか支店 (普) 12345678</td></tr>
      <tr><th>振込期限</th><td>2015年10月31日まで</td></tr>
    </table>
    </p>
TEXT;

    return array("msg"=>$page, "body"=>$content);

}