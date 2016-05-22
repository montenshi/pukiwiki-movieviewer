<?php

class MovieViewerNotifier {
    
    public function generateMessage($user, $context) {
    }
    
}

class MovieViewerPurchaseOfferNotifier extends MovieViewerNotifier {
    
    public function generateMessage($user, $context) {
        $settings = plugin_movieviewer_get_global_settings();
        $offer_maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);

        if (!$offer_maker->canOffer()) {
            return '';
        }

        $offer = $offer_maker->getOffer();

        plugin_movieviewer_purchase_start_set_back_page($context['back_page']);

        $req_params = "&deal_pack_id={$offer->getPackId()}";
        $start_uri_bank = plugin_movieviewer_get_script_uri() . "?{$context['start_page_bank']}&purchase_method=bank{$req_params}";
        $start_uri_credit = plugin_movieviewer_get_script_uri() . "?{$context['start_page_credit']}&purchase_method=credit{$req_params}";

        $hsc = "plugin_movieviewer_hsc";

        $bank_names_with_notes = nl2br($offer->getPaymentGuide()->bank_transfer->bank_names_with_notes);
        $price_with_notes = plugin_movieviewer_render_dealpack_offer_price($offer);

        if ($settings->payment->isCreditEnabled()) {
            $acceptable_brands = "";
            foreach($offer->getPaymentGuide()->credit_card->acceptable_brands as $brand) {
                $file_name = "logo_" . strtolower($brand) . ".gif";
                $acceptable_brand=<<<TEXT
                <img src="img/{$file_name}" ALT="{$brand}">
TEXT;
                $acceptable_brands .= $acceptable_brand;
            }
            
            $money_transfer_info =<<<TEXT
            <tr><th rowspan=2>振込先</th><th width=45%>利用可能な銀行</th><th width=45%>利用可能なクレジットカード</th></tr>
            <tr>
            <td>{$bank_names_with_notes}</td>
            <td style='vertical-align:top;'>{$acceptable_brands}</td>
            </tr>
TEXT;
        } else {
            $money_transfer_info =<<<TEXT
            <tr><th>振込先</th><td colspan=2>{$bank_names_with_notes}</td></tr>
TEXT;
        }

        $bank_transfer_info =<<<TEXT
        <p>
        <table class="movieviewer-payment-guide">
        <tr><th>項目</th><td colspan=2>{$hsc($offer->describePack())}</td></tr>
        <tr><th>金額</th><td colspan=2>{$price_with_notes}</td></tr>
        {$money_transfer_info}
        <tr><th>振込期限</th><td colspan=2>{$hsc($offer->getPaymentGuide()->deadline->format("Y年m月d日"))}まで</td></tr>
        </table>
        </p>
TEXT;

        $buttons_payment =<<<TEXT
        <a href="${start_uri_bank}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>銀行振り込みで申し込み</a>
TEXT;

        if ($settings->payment->isCreditEnabled()) {
            $buttons_payment .=<<<TEXT
            <a href="${start_uri_credit}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>クレジットカードで申し込み</a>
TEXT;
        }

        if ($offer->canDiscount()) {
            $discount_period = $offer->getDiscountPeriod();

            if ($offer->isFirstPurchase()) {
                $discount_message = "新規特別割引は{$hsc($discount_period->date_end->format('m月d日'))}までになります。この機会にぜひお申し込みください。";
            } else {
                $discount_message = "お得な継続割引は{$hsc($discount_period->date_end->format('m月d日'))}までになります。この機会にぜひ継続ください。";
            }

            $offer_message =<<<TEXT
            <p>
            {$hsc($offer->describePack())}の受講申し込みができるようになりました。
            </p>
            $bank_transfer_info
            <p>
            $discount_message
            </p>
            <p>
            $buttons_payment
            </p>
TEXT;
        } else {
            $offer_message =<<<TEXT
            <p>
            {$hsc($offer->describePack())}の受講申し込みができます。
            </p>
            $bank_transfer_info
            <p>
            $buttons_payment
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
}

class MovieViewerPurchaseStatusNotifier extends MovieViewerNotifier {

    public function generateMessage($user, $context) {
        $message = $this->generateMessageRequesting($user, $context);

        if ($message !== "") {
            return $message;
        }

        $message = $this->generateMessageConfirmed($user, $params);
        
        return $message;
    }
    
    private function generateMessageRequesting($user, $context) {
        $repo_req = plugin_movieviewer_get_deal_pack_purchase_request_repository();
        $objects = $repo_req->findRequestingByUser($user->id);

        if (count($objects) === 0) {
            return '';
        }
        
        $list = "";
        foreach ($objects as $object) {
            $list .= <<<TEXT
            <li>{$object->getPack()->describe()}<br>入金を確認中です。受講開始までお待ち下さい。</li>
TEXT;
        }

        $message =<<<TEXT
        <p>
        以下の受講セットを申し込んでいます。<br>
        </p>
        <ul>
            $list
        </ul>
TEXT;

        $content =<<<TEXT
        <div class="movieviewer-notice movieviewer-notice-purchase-status">
        $message
        </div>
TEXT;

        return $content;
    }

    private function generateMessageConfirmed($user, $context) {
        
        $repo_req = plugin_movieviewer_get_deal_pack_payment_confirmation_repository();
        $objects = $repo_req->findByNotYetStartedUser($user->id);

        if (count($objects) === 0) {
            return '';
        }

        $list = "";
        foreach ($objects as $object) {
            $message = "<br>入金が確認できました。<br>受講開始 {$object->getViewingPeriod()->date_begin->format('m月d日')} までもうしばらくお待ちください。<br>基礎コース１年目第１回～第４回以外は、受講開始のご連絡はいたしません。<br>期日になりましたら、各自受講を開始して下さい。";
            $list .= <<<TEXT
            <li>{$object->getPack()->describe()} {$message}</li>
TEXT;
        }

        $content =<<<TEXT
        <div class="movieviewer-notice movieviewer-notice-purchase-status">
        <ul>
        $list
        </ul>
        </div>
TEXT;

        return $content;
    }
}

class MovieViewerReportNotifier extends MovieViewerNotifier {
    public function generateMessage($user, $context) {
          return " <a href='https://ws.formzu.net/fgen/S75172099/' class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>Ｎ基礎コース1年目第５～８回レポート提出</a>";
              
    }
}
?>