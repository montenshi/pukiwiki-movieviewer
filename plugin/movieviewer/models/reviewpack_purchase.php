<?php

class MovieViewerReviewPackPurchaseRequest {
    public  $user_id;
    public  $purchase_method;
    private $review_pack;
    private $date_requested;

    function __construct($user_id, $purchase_method, $course_and_session_ids, $date_requested = NULL) {
        $this->user_id = $user_id;
        $this->purchase_method = $purchase_method;
        $this->review_pack = new MovieViewerReviewPack($course_and_session_ids);
        if ($date_requested == null) {
            $date_requested = plugin_movieviewer_now();
        }
        $this->date_requested = $date_requested;
    }

    function getItems() {
        return $this->review_pack->getItems();
    }

    function getItemsByCourse() {
        return $this->review_pack->getItemsByCourse();
    }

    function getPrice() {
        return $this->review_pack->getPrice();
    }

    function getDateRequested() {
        return $this->date_requested;
    }

    function preConfirmPayment() {
    }

    function confirmPayment() {
    }
}

class MovieViewerReviewPackPurchasePaymentGuide extends MovieViewerPaymentGuide {
    
    static function create($payment_settings, $request) {
        return new MovieViewerReviewPackPurchasePaymentGuide($payment_settings, $request->getDateRequested());
    }

    private $payment_settings;

    function __construct($payment_settings, $date_requested) {
        $this->payment_settings = $payment_settings;
        $bank_transfer = $this->getPaymentGuideBankTransfer();
        $credit_card = $this->getPaymentGuideCreditCard();
        $deadline = $this->getPaymentDeadline($date_requested);
        parent::__construct($bank_transfer, $credit_card, $deadline);
    }

    private function getPaymentDeadline($date_requested = NULL) {
        $tmp = $date_requested;
        if ($tmp === NULL) {
            // 省略されている場合は現在日時とする
            $tmp = new DateTime();
        }
        $last_day_of_this_month = new DateTime($tmp->modify("last day of this month")->format("Y-m-d 23:59:59"));
        return new MovieViewerTransferDeadline($last_day_of_this_month->format("Y-m-d H:i:sP"));
    }

    private function getPaymentGuideBankTransfer() {
        return new MovieViewerPaymentGuideBankTransfer(
                          $this->payment_settings->bank_transfer["bank_names"]
                        , $this->payment_settings->bank_transfer["bank_accounts"]
                        , $this->payment_settings->bank_transfer["notes"]
                    );
    }

    private function getPaymentGuideCreditCard() {
        $acceptable_brands = Array();
        if (isset($this->payment_settings->credit)) {
            $acceptable_brands = $this->payment_settings->credit->acceptable_brands;
        }
        return new MovieViewerPaymentGuideCreditCard($acceptable_brands);
    }
}

?>