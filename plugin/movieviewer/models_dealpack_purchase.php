<?php

class MovieViewerDealPackOffer {

    private $user;
    private $pack;
    private $discount_period;
    private $purchase_request = null;

    function __construct($user, $pack, $discount_period) {
        $this->user = $user;
        $this->pack = $pack;
        $this->discount_period = $discount_period;

        try {
            $purchase_request = plugin_movieviewer_get_deal_pack_purchase_request_repository()->findBy($user->id, $pack->getId());
            $this->purchase_request = $purchase_request;
        } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
            // 何もしない
        }
    }

    public function getPackId() {
        return $this->pack->getId();
    }

    public function getCourse() {
        return $this->pack->getCourse();
    }

    public function getSessions() {
        return $this->pack->getSessions();
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

    public function canDiscount() {
        return TRUE;
    }

    public function isAccepted() {
        return ($this->purchase_request != null);
    }

    public function accept() {
        $purchase_request = new MovieViewerDealPackPurchaseRequest($this->user->id, $this->pack->getId());

        plugin_movieviewer_get_deal_pack_purchase_request_repository()->store($purchase_request);

        $this->purchase_request = $purhase_request;
    }

    public function describePack() {
        return $this->pack->describe();
    }
}

class MovieViewerDealPackOfferMaker {
    private $user;
    private $purchase_histories;

    private $s4_packs;

    private $offers = array();

    function __construct($user) {
        $this->user = $user;
        $this->purchase_histories = array();
        $this->s4_container = new MovieViewerS4DealContainer();

        $this->createOffers();
    }

    public function canOffer() {
        return (count($this->offers) > 0);
    }

    public function getOffer() {
        return end($this->offers);
    }

    function createOffers() {
        $repo = plugin_movieviewer_get_deal_pack_payment_confirmation_repository();

        $year_month = date('Y-m');
        $discount_period = new MovieViewerDiscountPeriod(new DateTime(), new DateTime("last day of $year_month"));

        foreach($this->user->selected_courses as $selected_course) {
            $box = $this->s4_container->getBox($selected_course);
            $payment_confirmations = $repo->findByCourse($this->user->id, $selected_course);

            $payments = array();
            foreach($payment_confirmations as $payment_confirmation) {
                $payments[$payment_confirmation->pack_id] = $payment_confirmation;
            }

            foreach($box->packs as $pack) {
                if ($payments[$pack->getId()] !== NULL) {
                    // 直近に買ったパックの視聴期限が切れている場合はオファーの可能性があるので次に行く
                    if ($payments[$pack->getId()]->viewing_period->isExpired()) {
                        continue;
                    }

                    // 直近に買ったパックの視聴期限が1ヶ月前に迫っている場合は、次のパックのオファーをする
                    if ($payments[$pack->getId()]->viewing_period->aboutToExpire() === TRUE) {
                        continue;
                    }

                    break;
                }

                $offer = $this->createOffer($pack, $discount_period);
                $this->offers[] = $offer;
                break;
            }
        }
    }

    function createOffer($pack, $discount_period) {
        return new MovieViewerDealPackOffer(
                    $this->user
                  , $pack
                  , $discount_period
              );
    }
}

class MovieViewerDealPackPurchaseRequest {
    public $user_id;
    public $pack_id;
    public $date_requested;
    public $payment_confirmation;

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
        return plugin_movieviewer_get_deal_pack_repository()->findById($this->pack_id);
    }

    public function getDateRequested() {
        return $this->date_requested;
    }

    public function getPaymentConfirmation() {
        return $this->payment_confirmation;
    }

    public function getId() {
        return "{$this->user_id}###{$this->pack_id}";
    }

    public function confirmPayment() {
        $this->payment_confirmation = new MovieViewerDealPackPaymentConfirmation($this->user_id, $this->pack_id);
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
            $viewing_period["date_end"]->modify('+4 months');
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