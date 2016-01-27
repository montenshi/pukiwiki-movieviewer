<?php

class MovieViewerPaymentGuide {
    public $bank_transfer;
    public $credit_card;
    public $deadline;
    
    function __construct($bank_transfer, $credit_card, $deadline) {
        $this->bank_transfer = $bank_transfer;
        $this->credit_card = $credit_card;
        $this->deadline = $deadline;
    }
}

class MovieViewerPaymentGuideBankTransfer {
    public $bank_names;
    public $bank_accounts;
    public $notes;
    public $bank_names_with_notes;
    public $bank_accounts_with_notes;

    function __construct($bank_names, $bank_accounts, $notes) {
        $this->bank_names = $bank_names;
        $this->bank_accounts = $bank_accounts;
        $this->notes = $notes;
        $this->bank_names_with_notes = "{$this->bank_names}\n\n{$this->notes}";
        $this->bank_accounts_with_notes = "{$this->bank_accounts}\n\n{$this->notes}";
    }
}

class MovieViewerPaymentGuideCreditCard {
    public $acceptable_brands;

    function __construct($acceptable_brands) {
        $this->acceptable_brands = $acceptable_brands;
    }    
}

class MovieViewerDealPackOffer {

    private $user;
    private $pack;
    private $discount_period;
    private $payment_guide;
    private $purchase_request = null;

    function __construct($user, $pack, $discount_period, $payment_guide) {
        $this->user = $user;
        $this->pack = $pack;
        $this->discount_period = $discount_period;

        $this->payment_guide = $payment_guide;

        try {
            $purchase_request = plugin_movieviewer_get_deal_pack_purchase_request_repository()->findBy($user->id, $pack->getId());
            $this->purchase_request = $purchase_request;
        } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
            // 何もしない
        }
    }

    public function getPackName() {
        return $this->pack->describe();
    }

    public function getPackId() {
        return $this->pack->getId();
    }

    public function getCourse() {
        return $this->pack->getCourse();
    }

    public function getCourseId() {
        return $this->pack->getCourseId();
    }

    public function getCourseIdShort() {
        return $this->pack->getCourseIdShort();
    }

    public function getPackNumber() {
        return $this->pack->getPackNumber();
    }

    public function getSessions() {
        return $this->pack->getSessions();
    }

    public function getNumSessions() {
        return $this->pack->getNumSessions();
    }

    public function getDiscountPeriod() {
        return $this->discount_period;
    }

    public function getPrice() {
        if ($this->canDiscount()) {
            return $this->pack->getDiscountPrice();
        } else {
            return $this->pack->getFixedPrice();
        }
    }

    public function getPaymentGuide() {
        return $this->payment_guide;
    }

    public function canDiscount() {
        return $this->discount_period->canDiscount();
    }

    public function isAccepted() {
        return ($this->purchase_request != null);
    }

    public function accept() {
        $purchase_request = new MovieViewerDealPackPurchaseRequest($this->user->id, $this->pack->getId());

        plugin_movieviewer_get_deal_pack_purchase_request_repository()->store($purchase_request);

        $this->purchase_request = $purhase_request;
    }

    public function isFirstPurchase() {
        return ($this->getPackId() === 'K1Kiso-1');
    }

    public function describePack() {
        return $this->pack->describe();
    }

    public function describePackShort() {
        return $this->pack->describeShort();
    }
}

class MovieViewerDealPackOfferMaker {
    private $payment_settings;
    private $user;
    private $s4_packs;

    private $offers = array();

    function __construct($payment_settings, $user) {
        $this->payment_settings = $payment_settings;
        $this->user = $user;
        $this->s4_container = new MovieViewerS4DealContainer();

        $this->createOffers();
    }

    public function canOffer() {
        return (count($this->offers) > 0);
    }

    public function getOffer() {
        return end($this->offers);
    }

    public function getOffers() {
        return $this->offers;
    }

    function createOffers() {
        $repo = plugin_movieviewer_get_deal_pack_payment_confirmation_repository();

        foreach($this->user->selected_courses as $selected_course) {
            $box = $this->s4_container->getBox($selected_course);
            $payment_confirmations = $repo->findByCourse($this->user->id, $selected_course);

            $offer = $this->createOffer($this->user, $box, $payment_confirmations);

            if ($offer !== NULL) {
                $this->offers[] = $offer;
            }
        }
    }
    
    function createOffer($user, $box, $payment_confirmations) {
        
        $next_pack = $this->getNextPack($box, $payment_confirmations);        
        
        if ($next_pack === NULL) {
            return NULL;
        }
        
        $last_confirmation = $this->getLastConfirmation($box, $payment_confirmations);

        if (!$this->canStartOffering($last_confirmation)) {
            return NULL;
        }
        
        $discount_period = $this->getDiscountPeriod($next_pack, $last_confirmation);
        
        $payment_deadline = $this->getPaymentDeadline();
        
        $bank_transfer = new MovieViewerPaymentGuideBankTransfer(
                                  $this->payment_settings->bank_transfer["bank_names"]
                                , $this->payment_settings->bank_transfer["bank_accounts"]
                                , $this->payment_settings->bank_transfer["notes"]
                            );
                            
        $credit_card = new MovieViewerPaymentGuideCreditCard($this->payment_settings->credit->acceptable_brands);

        $payment_guide = new MovieViewerPaymentGuide($bank_transfer, $credit_card, $payment_deadline);
        
        $offer = new MovieViewerDealPackOffer(
              $user
            , $next_pack
            , $discount_period
            , $payment_guide
        );

        if ($offer->isAccepted()) {
            return NULL;
        }

        return $offer;
    }

    function getNextPack($box, $payment_confirmations) {

        // 何も購入していない場合は、最初のパック      
        if (count($payment_confirmations) === 0) {
            return reset($box->packs);
        }
        
        // 何かしら購入している場合は、最後に購入したパックの次
        $last_confirmation = $this->getLastConfirmation($box, $payment_confirmations);
        return $box->getNextPack($last_confirmation->pack_id);
    }
    
    function getDiscountPeriod($pack, $last_payment_confirmation) {
        
        /*
        if ($last_payment_confirmation !== NULL) {
            $date_end = $last_payment_confirmation->viewing_period->date_end;
            $date_begin = new DateTime($date_end->modify("first day of -1 month")->format("Y-m-d 23:59:59"));
            $date_end = new DateTime($date_end->modify("last day of this month")->format("Y-m-d 23:59:59"));

            return new MovieViewerDiscountPeriod($date_begin, $date_end);
        }
        */
        
        // 基礎1または、直近の視聴期限の1ヶ月前の場合は、割引を行う
        if ($pack->getId() === "K1Kiso-1" || 
            ( $last_payment_confirmation != NULL && $last_payment_confirmation->viewing_period->aboutToExpire())
           ) {
            $date_begin = new DateTime();
            $tmp = new DateTime();
            $date_end = new DateTime($tmp->modify("last day of this month")->format("Y-m-d 23:59:59"));
            return new MovieViewerDiscountPeriod($date_begin, $date_end);
        }

        // それ以外は割引しない
        return new MovieViewerNeverDiscountPeriod();
    }
    
    function getPaymentDeadline() {
        // 振込期限は今月の末日
        $tmp = new DateTime();
        $last_day_of_this_month = new DateTime($tmp->modify("last day of this month")->format("Y-m-d 23:59:59"));
        return new MovieViewerTransferDeadline($last_day_of_this_month->format("Y-m-d H:i:sP"));
    }
    
    function getLastConfirmation($box, $payment_confirmations) {
        $maped_confirmations = array();
        foreach($payment_confirmations as $payment_confirmation) {
            $maped_confirmations[$payment_confirmation->pack_id] = $payment_confirmation;
        }

        $last_confirmation = NULL;
        foreach($box->packs as $pack) {
            // 受講していないパックがあればそれを返す
            if (!isset($maped_confirmations[$pack->getId()])) {
                return $last_confirmation;
            }
            $last_confirmation = $maped_confirmations[$pack->getId()];
        }
        
        return $last_confirmation;
    }
    
    function canStartOffering($last_confirmation) {
        // 何も購入していない場合は、いつでもオファーを開始できる
        if ($last_confirmation === NULL) {
            return TRUE;
        }

        // 直近の視聴期限をすぎている場合は、いつでもオファーを開始できる
        if ($last_confirmation->viewing_period->isExpired()) {
            return TRUE;
        }
        
        // 直筋の視聴期限の1ヶ月前以降であれば、オファーを開始できる
        return $last_confirmation->viewing_period->aboutToExpire();
    }
    
}

class MovieViewerDealPackPurchaseRequest {

    public $user_id;
    public $pack_id;
    public $purchase_method;
    public $date_requested;
    public $payment_confirmation;

    public static function compareByMemberId($a, $b) {
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

    function __construct($user_id, $pack_id, $date_requested = null) {
        $this->user_id = $user_id;
        $this->pack_id = $pack_id;
        if ($date_requested == null) {
            $date_requested = plugin_movieviewer_now();
        }
        $this->date_requested = $date_requested;
    }

    public function getUser() {
        return plugin_movieviewer_get_user_repository()->findById($this->user_id);
    }

    public function getPack() {
        static $s4_container;
        if ($s4_container === NULL) {
            $s4_container = new MovieViewerS4DealContainer();
        }
        return $s4_container->getPack($this->pack_id);
    }

    public function getDateRequested() {
        return $this->date_requested;
    }

    public function getPaymentConfirmation() {
        if ($this->payment_confirmation !== NULL) {
            return $this->payment_confirmation;
        }

        return plugin_movieviewer_get_deal_pack_payment_confirmation_repository()->find($this->user_id, $this->pack_id);
    }

    public function getId() {
        return "{$this->user_id}###{$this->pack_id}";
    }

    public function isPaymentConfirmed() {
        if ($this->payment_confirmation !== NULL) {
            return TRUE;
        }

        return plugin_movieviewer_get_deal_pack_payment_confirmation_repository()->exists($this->user_id, $this->pack_id);
    }

    public function preConfirmPayment() {
        $payment_confirmation = new MovieViewerDealPackPaymentConfirmation($this->user_id, $this->pack_id);
        return $payment_confirmation;
    }

    public function confirmPayment() {
        $this->payment_confirmation = $this->preConfirmPayment();
        $periods = $this->addViewingPeriods($this->payment_confirmation);

        plugin_movieviewer_get_viewing_periods_by_user_repository()->store($periods);
        plugin_movieviewer_get_deal_pack_payment_confirmation_repository()->store($this->payment_confirmation);

        return $this->payment_confirmation;
    }

    function addViewingPeriods($payment_confirmation) {

        $periods = plugin_movieviewer_get_viewing_periods_by_user_repository()->findById($payment_confirmation->getUser()->id);

        $course_id = $payment_confirmation->getPack()->getCourse()->id;
        $sessions = $payment_confirmation->getPack()->getSessions();
        $date_begin = $payment_confirmation->getViewingPeriod()->date_begin;
        $date_end = $payment_confirmation->getViewingPeriod()->date_end;

        foreach($sessions as $session) {
            $periods->addPeriod($course_id, $session->id, $date_begin, $date_end);
        }

        return $periods;
    }
}

class MovieViewerDealPackPaymentConfirmation {
    public $user_id;
    public $pack_id;
    public $date_confirmed;
    public $viewing_period;

    function __construct($user_id, $pack_id, $date_confirmed = null, $viewing_period = array()) {
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

    public function getUser() {
        return plugin_movieviewer_get_user_repository()->findById($this->user_id);
    }

    public function getPack() {
        return plugin_movieviewer_get_deal_pack_repository()->findById($this->pack_id);
    }

    public function getDateConfirmed() {
        return $this->date_confirmed;
    }

    public function getViewingPeriod() {
        return $this->viewing_period;
    }
}

?>