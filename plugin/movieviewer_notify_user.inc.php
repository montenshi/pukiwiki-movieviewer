<?php

require_once("movieviewer.ini.php");
require_once("movieviewer_purchase_start.inc.php");
require_once("movieviewer_purchase_notify_payment.inc.php");

function plugin_movieviewer_notify_user_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_notify_user_convert(){

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return '';
    }

    if ($user->isAdmin()) {
        return '';
    }

    $page_args = func_get_args();
    $params = array();
    $params['start_page_bank']   = $page_args[0];
    $params['start_page_credit'] = $page_args[1];
    $params['back_page'] = $page_args[2];

    global $defaultpage;
    if (!isset($params['back_page'])) {
        $params['back_page'] = $defaultpage;
    }

    $purchase_offer = plugin_movieviewer_notify_user_convert_purchase_offer($user, $params);

    $purchase_status = plugin_movieviewer_notify_user_convert_purchase_status($user, $params);

    if ($purchase_offer === "" && $purchase_status === "") {
        return '';
    }

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>お知らせ</h2>
    <div class="movieviewer-notices">
    $purchase_offer
    $purchase_status
    </div>
TEXT;

    return $content;
}

function plugin_movieviewer_notify_user_convert_purchase_offer($user, $params) {

    $settings = plugin_movieviewer_get_global_settings();
    $offer_maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);

    if (!$offer_maker->canOffer()) {
        return '';
    }

    $offer = $offer_maker->getOffer();

    if ($offer->isAccepted()) {
        return '';
    }

    plugin_movieviewer_purchase_start_set_back_page($params['back_page']);

    $req_params = "&purchase_method=bank&deal_pack_id={$offer->getPackId()}";
    $start_uri_bank = plugin_movieviewer_get_script_uri() . "?{$params['start_page_bank']}{$req_params}";
    $start_uri_credit = plugin_movieviewer_get_script_uri() . "?{$params['start_page_credit']}{$req_params}";

    $hsc = "plugin_movieviewer_hsc";

    if ($offer->canDiscount()) {
        $discount_period = $offer->getDiscountPeriod();

        if ($offer->isFirstPurchase()) {
            $discount_message = "新規特別割引は{$hsc($discount_period->date_end->format('m月d日'))}までになります。この機会にぜひお申し込みください。";
        } else {
            $discount_message = "お得な継続割引は{$hsc($discount_period->date_end->format('m月d日'))}までになります。この機会にぜひ継続ください。";
        }

        $offer_message =<<<TEXT
        <p>
        {$hsc($offer->describePack())}の受講ができるようになりました。<br>
        $discount_message
        </p>
        <p>
        <a href="${start_uri_bank}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>銀行振り込みで申し込み</a>
        </p>
TEXT;

    } else {
        $offer_message =<<<TEXT
        <p>
        {$hsc($offer->describePack())}の受講ができます。<br>
        </p>
        <p>
        <a href="${start_uri_bank}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>銀行振り込みで申し込み</a>
        </p>
TEXT;
    }

    $content =<<<TEXT
    <div class="movieviewer-notice movieviewer-notice-purchase-offer">
      $offer_message
    </div>
TEXT;

    return $content;
}

function plugin_movieviewer_notify_user_convert_purchase_status($user, $params) {
    $repo_req = plugin_movieviewer_get_deal_pack_purchase_request_repository();

    $objects = $repo_req->findRequestingByUser($user->id);

    if (count($objects) === 0) {
        return '';
    }

    $list = "";
    foreach ($objects as $object) {
        if ($object->isNotified()) {
            $message = "<br>入金を確認中です。受講開始までお待ち下さい。";
        } else {
            $link = "/index.php?cmd=movieviewer_purchase_notify_payment&deal_pack_id={$object->pack_id}";
            $message = "<a href='{$link}' class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>入金の完了を通知する</a>";
        }

        $list .= <<<TEXT
        <li>{$object->getPack()->describe()} {$message}</li>
TEXT;
    }

    if ($list === "") {
        return '';
    }

    plugin_movieviewer_purchase_notify_payment_set_back_page($params['back_page']);

    $message =<<<TEXT
    <p>
    以下の単元を申し込んでいます。
    <ul>
        $list
    </ul>
    </p>
TEXT;

    $content =<<<TEXT
    <div class="movieviewer-notice movieviewer-notice-purchase-status">
      $message
    </div>
TEXT;

    return $content;
}

?>