<?php

/**
 * Pukiwikiプラグイン::動画視聴 受講申し込み
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.DealPack
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerPaymentGuide
{
    public $bank_transfer;
    public $credit_card;
    public $deadline;
    
    function __construct($bank_transfer, $credit_card, $deadline)
    {
        $this->bank_transfer = $bank_transfer;
        $this->credit_card = $credit_card;
        $this->deadline = $deadline;
    }
}

class MovieViewerPaymentGuideBankTransfer
{
    public $bank_names;
    public $bank_accounts;
    public $notes;
    public $bank_names_with_notes;
    public $bank_accounts_with_notes;

    function __construct($bank_names, $bank_accounts, $notes)
    {
        $this->bank_names = $bank_names;
        $this->bank_accounts = $bank_accounts;
        $this->notes = $notes;
        $this->bank_names_with_notes = "{$this->bank_names}\n\n{$this->notes}";
        $this->bank_accounts_with_notes = "{$this->bank_accounts}\n\n{$this->notes}";
    }
}

class MovieViewerPaymentGuideCreditCard
{
    public $acceptable_brands;

    function __construct($acceptable_brands)
    {
        $this->acceptable_brands = $acceptable_brands;
    }    
}

class MovieViewerDealPackOffer
{
    private $_user;
    private $_pack;
    private $_discount_period;
    private $_payment_guide;
    private $_purchase_request = null;

    function __construct($user, $pack, $discount_period, $payment_guide)
    {
        $this->_user = $user;
        $this->_pack = $pack;
        $this->_discount_period = $discount_period;

        $this->_payment_guide = $payment_guide;

        try {
            $purchase_request = plugin_movieviewer_get_deal_pack_purchase_request_repository()->findBy($user->id, $pack->getId());
            $this->_purchase_request = $purchase_request;
        } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
            // 何もしない
        }
    }

    function getPackName()
    {
        return $this->_pack->describe();
    }

    function getPackId()
    {
        return $this->_pack->getId();
    }

    function getCourse()
    {
        return $this->_pack->getCourse();
    }

    function getCourseId()
    {
        return $this->_pack->getCourseId();
    }

    function getCourseIdShort()
    {
        return $this->_pack->getCourseIdShort();
    }

    function getPackNumber()
    {
        return $this->_pack->getPackNumber();
    }

    function getSessions()
    {
        return $this->_pack->getSessions();
    }

    function getNumSessions()
    {
        return $this->_pack->getNumSessions();
    }

    function getDiscountPeriod()
    {
        return $this->_discount_period;
    }

    function getPrice()
    {
        if ($this->canDiscount()) {
            return $this->_pack->getDiscountPrice();
        } else {
            return $this->_pack->getFixedPrice();
        }
    }

    function getPaymentGuide()
    {
        return $this->_payment_guide;
    }

    function canDiscount()
    {
        return $this->_discount_period->canDiscount();
    }

    function isAccepted()
    {
        return ($this->_purchase_request != null);
    }

    function accept()
    {
        $purchase_request = new MovieViewerDealPackPurchaseRequest($this->_user->id, $this->_pack->getId());

        plugin_movieviewer_get_deal_pack_purchase_request_repository()->store($purchase_request);

        $this->_purchase_request = $purhase_request;
    }

    function isFirstPurchase()
    {
        return ($this->getPackId() === 'K1Kiso-1');
    }

    function describePack()
    {
        return $this->_pack->describe();
    }

    function describePackShort()
    {
        return $this->_pack->describeShort();
    }
}

class MovieViewerDealPackOfferMaker
{
    private $_payment_settings;
    private $_user;
    private $_s4_packs;

    private $_offers = array();

    function __construct($payment_settings, $user)
    {
        $this->_payment_settings = $payment_settings;
        $this->_user = $user;
        $this->_s4_container = new MovieViewerS4DealContainer();

        $this->createOffers();
    }

    function canOffer()
    {
        return (count($this->_offers) > 0);
    }

    function getOffer()
    {
        return end($this->_offers);
    }

    function getOffers()
    {
        return $this->_offers;
    }

    function createOffers()
    {
        foreach ($this->_user->selected_routes as $route) {
            // 最後に購入したパックを見つける
            $last_confirmation = $this->getLastConfirmationInRoute($this->_user, $route);

            // Offer開始ができない場合は何もしない
            if (!$this->canStartOffering($last_confirmation)) {
                continue;
            }

            // 次のBoxとPackを取得する
            $next = $this->getNextPackAndBox($route, $this->_s4_container, $last_confirmation);
            $next_pack = $next['pack'];

            if ($next === null) {
                continue;
            }

            $offer = $this->createOffer($this->_user, $next_pack, $last_confirmation);

            if ($offer->isAccepted()) { // 受け入れられている場合は何もしない
                continue;
            }

            $this->_offers[] = $offer;
        }
    }

    private function getLastConfirmationInRoute($user, $route)
    {
        $confirmations = array();
        $course_ids_reversed = array_reverse($route->course_ids);
        foreach ($course_ids_reversed as $course_id) {
            $confirmations = plugin_movieviewer_get_deal_pack_payment_confirmation_repository()->findByCourse($user->id, $course_id);

            if (count($confirmations) > 0) {
                break;
            }
        }

        if (count($confirmations) === 0) {
            return null;
        }

        return end($confirmations);
    }

    private function getNextPackAndBox($route, $s4_container, $last_confirmation)
    {
        if ($last_confirmation) { // 何か買っていた場合
            $current_pack = $last_confirmation->getPack();

            // 現在のBoxを取り出す
            $next_box = $s4_container->getBox($current_pack->getCourseId());
            $next_pack = $next_box->getNextPack($current_pack->getId());

            // Boxの最終パックを購入済みの場合は、次のBoxが対象となる
            if ($next_box->getLastPack()->getId() === $current_pack->getId()) {
                $next_course_id = $route->getNext($current_pack->getCourseId());

                if ($next_course_id === null) {
                    return null;
                }

                $next_box = $s4_container->getBox($next_course_id);

                if ($next_box === null) {
                    return null;
                }

                $next_pack = $next_box->getFirstPack();
            }
        } else { // 何も買っていなかった場合は、最初のBoxが対象
            $next_box = $s4_container->getBox($route->getFirst());

            if ($next_box === null) {
                return null;
            }

            $next_pack = $next_box->getFirstPack();
        }

        $result['box'] = $next_box;
        $result['pack'] = $next_pack;
        
        return $result;
    }
    
    private function createOffer($user, $next_pack, $last_confirmation)
    {
        $discount_period = $this->getDiscountPeriod($next_pack, $last_confirmation);
        
        $payment_deadline = $this->getPaymentDeadline();
        
        $bank_transfer = new MovieViewerPaymentGuideBankTransfer(
            $this->_payment_settings->bank_transfer["bank_names"],
            $this->_payment_settings->bank_transfer["bank_accounts"],
            $this->_payment_settings->bank_transfer["notes"]
        );

        $acceptable_brands = Array();
        if (isset($this->_payment_settings->credit)) {
            $acceptable_brands = $this->_payment_settings->credit->acceptable_brands;
        }
        $credit_card = new MovieViewerPaymentGuideCreditCard($acceptable_brands);

        $payment_guide = new MovieViewerPaymentGuide($bank_transfer, $credit_card, $payment_deadline);
        
        $offer = new MovieViewerDealPackOffer(
            $user,
            $next_pack,
            $discount_period,
            $payment_guide
        );

        return $offer;
    }

    private function getDiscountPeriod($pack, $last_payment_confirmation)
    {
        // 直近の視聴期限の前月1日から前月末日の場合は、割引を行う
        if ($last_payment_confirmation !== null) {
            $date_begin = plugin_movieviewer_get_first_day_of_last_month($last_payment_confirmation->viewing_period->date_end);
            $date_end   = plugin_movieviewer_get_last_day_of_same_month($date_begin);

            return new MovieViewerDiscountPeriod($date_begin, $date_end);
        }
        
        // 基礎1の場合は、割引を行う
        if ($pack->getId() === "K1Kiso-1") {
            $date_begin = new DateTime();
            $date_end   = plugin_movieviewer_get_last_day_of_same_month($date_begin);
            return new MovieViewerDiscountPeriod($date_begin, $date_end);
        }

        // それ以外は割引しない
        return new MovieViewerNeverDiscountPeriod();
    }
    
    private function getPaymentDeadline()
    {
        // 振込期限は今月の末日
        $tmp = new DateTime();
        $last_day_of_this_month = new DateTime($tmp->modify("last day of this month")->format("Y-m-d 23:59:59"));
        return new MovieViewerTransferDeadline($last_day_of_this_month->format("Y-m-d H:i:sP"));
    }
    
    private function canStartOffering($last_confirmation)
    {
        // 何も購入していない場合は、いつでもオファーを開始できる
        if ($last_confirmation === null) {
            return true;
        }

        // 直近の視聴期限をすぎている場合は、いつでもオファーを開始できる
        if ($last_confirmation->viewing_period->isExpired()) {
            return true;
        }
        
        // 直近の視聴期限の前月1日以降であれば、オファーを開始できる
        $first_day_of_last_month = plugin_movieviewer_get_first_day_of_last_month($last_confirmation->viewing_period->date_end);
        return (new DateTime() >= $first_day_of_last_month);
    }
    
}

class MovieViewerDealPackPurchaseRequest
{
    public $user_id;
    public $pack_id;
    public $purchase_method;
    public $date_requested;
    public $payment_confirmation;

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

    function __construct($user_id, $pack_id, $date_requested = null)
    {
        $this->user_id = $user_id;
        $this->pack_id = $pack_id;
        if ($date_requested == null) {
            $date_requested = plugin_movieviewer_now();
        }
        $this->date_requested = $date_requested;
    }

    function getUser()
    {
        return plugin_movieviewer_get_user_repository()->findById($this->user_id);
    }

    function getPack()
    {
        static $s4_container;
        if ($s4_container === null) {
            $s4_container = new MovieViewerS4DealContainer();
        }
        return $s4_container->getPack($this->pack_id);
    }

    function getDateRequested()
    {
        return $this->date_requested;
    }

    function getPaymentConfirmation() 
    {
        if ($this->payment_confirmation !== null) {
            return $this->payment_confirmation;
        }

        return plugin_movieviewer_get_deal_pack_payment_confirmation_repository()->find($this->user_id, $this->pack_id);
    }

    function getId()
    {
        return "{$this->user_id}###{$this->pack_id}";
    }

    function isPaymentConfirmed()
    {
        if ($this->payment_confirmation !== null) {
            return true;
        }

        return plugin_movieviewer_get_deal_pack_payment_confirmation_repository()->exists($this->user_id, $this->pack_id);
    }

    function preConfirmPayment($date_begin = null)
    {
        $viewing_period = Array();
        $viewing_period["date_begin"] = $date_begin;
        $payment_confirmation = new MovieViewerDealPackPaymentConfirmation($this->user_id, $this->pack_id, $viewing_period);
        return $payment_confirmation;
    }

    function confirmPayment($date_begin = null)
    {
        $this->payment_confirmation = $this->preConfirmPayment($date_begin);
        $periods = $this->addViewingPeriods($this->payment_confirmation);

        plugin_movieviewer_get_viewing_periods_by_user_repository()->store($periods);
        plugin_movieviewer_get_deal_pack_payment_confirmation_repository()->store($this->payment_confirmation);

        return $this->payment_confirmation;
    }

    private function addViewingPeriods($payment_confirmation)
    {
        $periods = plugin_movieviewer_get_viewing_periods_by_user_repository()->findById($payment_confirmation->getUser()->id);

        $course_id = $payment_confirmation->getPack()->getCourse()->id;
        $sessions = $payment_confirmation->getPack()->getSessions();
        $date_begin = $payment_confirmation->getViewingPeriod()->date_begin;
        $date_end = $payment_confirmation->getViewingPeriod()->date_end;

        foreach ($sessions as $session) {
            $periods->addPeriod($course_id, $session->id, $date_begin, $date_end);
        }

        return $periods;
    }
}

class MovieViewerDealPackPaymentConfirmation
{
    public $user_id;
    public $pack_id;
    public $date_confirmed;
    public $viewing_period;

    function __construct($user_id, $pack_id, $viewing_period = array(), $date_confirmed = null)
    {
        $this->user_id = $user_id;
        $this->pack_id = $pack_id;
        if ($date_confirmed == null) {
            $date_confirmed = plugin_movieviewer_now();
        }
        $this->date_confirmed = $date_confirmed;

        if ($viewing_period["date_begin"] === null) {
            $viewing_period["date_begin"] = new DateTime($this->date_confirmed->format("Y-m-15 00:00:00P"));
        }

        if ($viewing_period["date_end"] === null) {
            $viewing_period["date_end"] = new DateTime($viewing_period["date_begin"]->format("Y-m-d 00:00:00P"));
            $viewing_period["date_end"]->modify('+4 months')->modify('-1 sec'); // 前日までを 23:59:59 までとする
        }

        $this->viewing_period = new MovieViewerPeriod($viewing_period["date_begin"], $viewing_period["date_end"]);
    }

    function getUser()
    {
        return plugin_movieviewer_get_user_repository()->findById($this->user_id);
    }

    function getPack()
    {
        return plugin_movieviewer_get_deal_pack_repository()->findById($this->pack_id);
    }

    function getDateConfirmed()
    {
        return $this->date_confirmed;
    }

    function getViewingPeriod()
    {
        return $this->viewing_period;
    }
}

class MovieViewerDealPackBankTransferInformationMailBuilder extends MovieViewerMailBuilder
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function build($user, $deal_pack_name, $price, $bank_transfer, $deadline)
    {
        $settings_local = $this->settings->template["transfer_information"];
        $mail = $this->createMail($user->mailAddress);

        $params = array(
              "user_name" => $user->describe()
            , "deal_pack_name" => $deal_pack_name
            , "bank_accounts_with_notes" => "{$bank_transfer->bank_accounts_with_notes}"
            , "deadline" => $deadline->format("Y年m月d日")
            , "price" => $price
        );

        $body = $this->renderBody($settings_local["body"], $params);

        $mail->Subject = $settings_local["subject"];
        $mail->Body = $body;
        return $mail;
    }

}

?>