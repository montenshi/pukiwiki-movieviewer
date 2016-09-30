<?php

/**
 * Pukiwikiプラグイン::動画視聴 再視聴
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.Review
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerReviewPackPurchaseRequest
{
    static function requestsHasItem($requests, $course_id, $session_id)
    {
        foreach ($requests as $request) {
            if ($request->hasItem($course_id, $session_id)) {
                return true;
            }
        }
        return false;
    }

    public  $user_id;
    public  $purchase_method;
    public  $review_pack;
    public  $date_requested;
    private $_user = null;

    static function compareByMemberId($a, $b)
    {
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

    function __construct($user_id, $purchase_method, $item_ids, $date_requested = null)
    {
        $this->user_id = $user_id;
        $this->purchase_method = $purchase_method;
        $this->review_pack = new MovieViewerReviewPack($item_ids);
        if ($date_requested == null) {
            $date_requested = plugin_movieviewer_now();
        }
        $this->date_requested = $date_requested;
    }

    function getId()
    {
        return "{$this->user_id}###{$this->date_requested->format(MovieViewerReviewPackPurchaseRequestRepositoryInFile::PATH_DATETIME_FORMAT)}";
    }

    function getUser()
    {
        if ($this->_user === null) {
            $this->_user = plugin_movieviewer_get_user_repository()->findById($this->user_id);
        }
        return $this->_user;
    }

    function getItems()
    {
        return $this->review_pack->getItems();
    }

    function getItemsByCourse()
    {
        return $this->review_pack->getItemsByCourse();
    }

    function getPrice()
    {
        return $this->review_pack->getPrice();
    }

    function describePack()
    {
        return $this->review_pack->describe();
    }

    function describePackShort()
    {
        return $this->review_pack->describeShort();
    }

    function getDateRequested()
    {
        return $this->date_requested;
    }

    function hasItem($course_id, $session_id)
    {
        return $this->review_pack->hasItem($course_id, $session_id);
    }
}

class MovieViewerReviewPackPaymentConfirmation
{
    static function createFromRequest($request, $date_begin)
    {
        $viewing_period = Array();
        $viewing_period["date_begin"] = $date_begin;
        $item_ids = array();
        foreach ($request->getItems() as $item) {
            $item_ids[] = $item->getId();
        }

        $object = new MovieViewerReviewPackPaymentConfirmation(
            $request->user_id,
            $request->purchase_method,
            $item_ids,
            $request->getDateRequested(),
            null,
            $viewing_period
        );

        return $object;
    }

    public $user_id;
    public $purchase_method;
    public $review_pack;
    public $viewing_period;
    public $date_requested;
    public $date_confirmed;

    function __construct($user_id, $purchase_method, $item_ids, $date_requested, $date_confirmed = null, $viewing_period = array()) {
        $this->user_id = $user_id;
        $this->purchase_method = $purchase_method;
        $this->review_pack = new MovieViewerReviewPack($item_ids);
        $this->date_requested = $date_requested;
        if ($date_confirmed == null) {
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

    function getUser()
    {
        return plugin_movieviewer_get_user_repository()->findById($this->user_id);
    }

    function getItems()
    {
        return $this->review_pack->getItems();
    }

    function getItemsByCourse()
    {
        return $this->review_pack->getItemsByCourse();
    }

    function getPack()
    {
        return $this->review_pack;
    }

    function getDateConfirmed()
    {
        return $this->date_confirmed;
    }

    function getViewingPeriod()
    {
        return $this->viewing_period;
    }

    function describePack()
    {
        return $this->review_pack->describe();
    }
}

class MovieViewerReviewPackPurchasePaymentGuide extends MovieViewerPaymentGuide
{
    static function create($payment_settings, $request)
    {
        return new MovieViewerReviewPackPurchasePaymentGuide($payment_settings, $request->getDateRequested());
    }

    private $_payment_settings;

    function __construct($payment_settings, $date_requested)
    {
        $this->_payment_settings = $payment_settings;
        $bank_transfer = $this->getPaymentGuideBankTransfer();
        $credit_card = $this->getPaymentGuideCreditCard();
        $deadline = $this->getPaymentDeadline($date_requested);
        parent::__construct($bank_transfer, $credit_card, $deadline);
    }

    private function getPaymentDeadline($date_requested = null)
    {
        $tmp = $date_requested;
        if ($tmp === null) {
            // 省略されている場合は現在日時とする
            $tmp = new DateTime();
        }
        $last_day_of_this_month = new DateTime($tmp->modify("+10 day")->format("Y-m-d 23:59:59"));
        return new MovieViewerTransferDeadline($last_day_of_this_month->format("Y-m-d H:i:sP"));
    }

    private function getPaymentGuideBankTransfer()
    {
        return new MovieViewerPaymentGuideBankTransfer(
            $this->_payment_settings->bank_transfer["bank_names"],
            $this->_payment_settings->bank_transfer["bank_accounts"],
            $this->_payment_settings->bank_transfer["notes"]
        );
    }

    private function getPaymentGuideCreditCard()
    {
        $acceptable_brands = Array();
        if (isset($this->_payment_settings->credit)) {
            $acceptable_brands = $this->_payment_settings->credit->acceptable_brands;
        }
        return new MovieViewerPaymentGuideCreditCard($acceptable_brands);
    }
}

class MovieViewerReviewPackBankTransferInformationMailBuilder extends MovieViewerMailBuilder
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function build($user, $item_names, $price, $bank_transfer, $deadline)
    {
        $template = $this->settings->template["reviewpack_transfer_information"];
        $mail = $this->createMail($user->mailAddress);

        $params = array(
            "user_name" => $user->describe(),
            "item_names" => implode("\n", $item_names),
            "bank_accounts_with_notes" => "{$bank_transfer->bank_accounts_with_notes}",
            "deadline" => $deadline->format("Y年m月d日"),
            "price" => $price
        );

        $body = $this->renderBody($template["body"], $params);

        $mail->Subject = $template["subject"];
        $mail->Body = $body;

        return $mail;
    }

}

class MovieViewerReviewPackRequestNotificationMailBuilder extends MovieViewerMailBuilder
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function build($user, $item_names, $price, $bank_transfer, $deadline)
    {
        $template = $this->settings->template["reviewpack_request_notifycation"];
        $mail = $this->createMail($template["to"]);

        $params = array(
            "user_name" => $user->describe(),
            "item_count" => count($item_names),
            "item_names" => implode("\n", $item_names),
            "bank_accounts_with_notes" => "{$bank_transfer->bank_accounts_with_notes}",
            "deadline" => $deadline->format("Y年m月d日"),
            "price" => $price
        );

        $subject = $this->renderBody($template["subject"], $params);
        $body = $this->renderBody($template["body"], $params);

        $mail->Subject = $subject;
        $mail->Body = $body;

        return $mail;
    }

}

?>