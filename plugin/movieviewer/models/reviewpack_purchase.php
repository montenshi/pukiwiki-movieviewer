<?php

class MovieViewerReviewPackPurchaseRequest {
    public  $user_id;
    public  $purchase_method;
    private $review_pack;
    private $date_requested;
    private $user = NULL;

    static function compareByMemberId($a, $b) {
        $aUser = $a->getUser();
        $bUser = $b->getUser();
        if (!$aUser->hasMemberId()) {
            if ($bUser->hasMemberId()) {
                return -1;
            } else {
                return 0;
            }
        }
        if ($aUser->memberId === $bUser->memberId) {
            return 0;
        }
        return ($aUser->memberId < $bUser->memberId) ? -1 : 1;
    }

    function __construct($user_id, $purchase_method, $item_ids, $date_requested = NULL) {
        $this->user_id = $user_id;
        $this->purchase_method = $purchase_method;
        $this->review_pack = new MovieViewerReviewPack($item_ids);
        if ($date_requested == null) {
            $date_requested = plugin_movieviewer_now();
        }
        $this->date_requested = $date_requested;
    }

    function getId() {
        return "{$this->user_id}###{$this->date_requested->format(MovieViewerReviewPackPurchaseRequestRepositoryInFile::PATH_DATETIME_FORMAT)}";
    }

    function getUser() {
        if ($this->user === NULL) {
            $this->user = plugin_movieviewer_get_user_repository()->findById($this->user_id);
        }
        return $this->user;
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

    function describePack() {
        return $this->review_pack->describe();
    }

    function describePackShort() {
        return $this->review_pack->describeShort();
    }

    function getDateRequested() {
        return $this->date_requested;
    }
}

class MovieViewerReviewPackPaymentConfirmation {

    static function createFromRequest($request, $date_begin) {
        $viewing_period = Array();
        $viewing_period["date_begin"] = $date_begin;
        $item_ids = array();
        foreach($request->getItems() as $item) {
            $item_ids[] = $item->getId();
        }

        $object = 
            new MovieViewerReviewPackPaymentConfirmation(
                  $request->user_id
                , $request->purchase_method
                , $item_ids
                , $request->getDateRequested()
                , NULL
                , $viewing_period
            );
        return $object;
    }

    public $user_id;
    public $purchase_method;
    public $review_pack;
    public $viewing_period;
    public $date_requested;
    public $date_confirmed;

    function __construct($user_id, $purchase_method, $item_ids, $date_requested, $date_confirmed = NULL, $viewing_period = array()) {
        $this->user_id = $user_id;
        $this->purchase_method = $purchase_method;
        $this->review_pack = new MovieViewerReviewPack($item_ids);
        $this->date_requested = $date_requested;
        if ($date_confirmed == NULL) {
            $date_confirmed = plugin_movieviewer_now();
        }
        $this->date_confirmed = $date_confirmed;

        if (!isset($viewing_period["date_begin"])) {
            $viewing_period["date_begin"] = new DateTime($this->date_confirmed->format("Y-m-d 00:00:00P"));
            $viewing_period["date_begin"]->modify('+2 days');
        }

        if (!isset($viewing_period["date_end"])) {
            $viewing_period["date_end"] = new DateTime($viewing_period["date_begin"]->format("Y-m-d 00:00:00P"));
            $viewing_period["date_end"]->modify('+1 months')->modify('-1 sec'); // 前日までを 23:59:59 までとする
        }

        $this->viewing_period = new MovieViewerPeriod($viewing_period["date_begin"], $viewing_period["date_end"]);
    }

    public function getUser() {
        return plugin_movieviewer_get_user_repository()->findById($this->user_id);
    }

    function getItems() {
        return $this->review_pack->getItems();
    }

    function getItemsByCourse() {
        return $this->review_pack->getItemsByCourse();
    }

    public function getPack() {
        return $this->review_pack;
    }

    public function getDateConfirmed() {
        return $this->date_confirmed;
    }

    public function getViewingPeriod() {
        return $this->viewing_period;
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

class MovieViewerReviewPackBankTransferInformationMailBuilder extends MovieViewerMailBuilder {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function build($user, $item_names, $price, $bank_transfer, $deadline) {

        $template = $this->settings->template["reviewpack_transfer_information"];
        $mail = $this->createMail($user->mailAddress);

        $params = array(
              "user_name" => $user->describe()
            , "item_names" => implode("\n", $item_names)
            , "bank_accounts_with_notes" => "{$bank_transfer->bank_accounts_with_notes}"
            , "deadline" => $deadline->format("Y年m月d日")
            , "price" => $price
        );

        $body = $this->renderBody($template["body"], $params);

        $mail->Subject = $template["subject"];
        $mail->Body = $body;
        return $mail;
    }

}

class MovieViewerReviewPackRequestNotificationMailBuilder extends MovieViewerMailBuilder {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function build($user, $item_names, $price, $bank_transfer, $deadline) {

        $template = $this->settings->template["reviewpack_request_notifycation"];
        $mail = $this->createMail($template["to"]);

        $params = array(
              "user_name" => $user->describe()
            , "item_count" => count($item_names)
            , "item_names" => implode("\n", $item_names)
            , "bank_accounts_with_notes" => "{$bank_transfer->bank_accounts_with_notes}"
            , "deadline" => $deadline->format("Y年m月d日")
            , "price" => $price
        );

        $subject = $this->renderBody($template["subject"], $params);
        $body = $this->renderBody($template["body"], $params);

        $mail->Subject = $subject;
        $mail->Body = $body;
        return $mail;
    }

}

?>