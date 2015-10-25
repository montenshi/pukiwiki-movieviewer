<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_purchase_notify_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_purchase_notify_convert(){

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return '';
    }

    if ($user->isAdmin()) {
        return '';
    }

    $settings = plugin_movieviewer_get_global_settings();
    $offer_maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);

    if (!$offer_maker->canOffer()) {
        return '';
    }

    $offer = $offer_maker->getOffer();

    if ($offer->isAccepted()) {
        return '';
    }

    $page_args = func_get_args();
    $start_pages = array();
    $start_pages['bank']   = $page_args[0];
    $start_pages['credit'] = $page_args[1];

    $start_uri_bank = get_script_uri() . "?${start_pages['bank']}&purchase_method=bank&deal_pack_id={$offer->getPackId()}";
    $start_uri_credit = get_script_uri() . "?${start_pages['credit']}&purchase_method=credit&deal_pack_id={$offer->getPackId()}";

    if ($offer->canDiscount()) {
        $discount_period = $offer->getDiscountPeriod();
        $content =<<<TEXT
        <div>
        <p>
        {$offer->describePack()}の受講ができるようになりました。<br>
        お得な継続割引は{$discount_period->date_end->format("m月d日")}までになります。この機会にぜひ継続ください。
        </p>
        <p>
        <a href="${start_uri_bank}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>銀行振り込みで申し込み</a>
        <a href="${start_uri_credit}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>クレジットカードで申し込み</a>
        </p>
        </div>
TEXT;

    } else {
        $content =<<<TEXT
        <div>
        <p>
        {$offer->describePack()}の受講ができます。<br>
        </p>
        <p>
        <a href="${start_uri_bank}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>銀行振り込みで申し込み</a>
        <a href="${start_uri_credit}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>クレジットカードで申し込み</a>
        </p>
        </div>
TEXT;
    }

    $title =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>お知らせ</h2>
TEXT;

    return $title . $content;
}