<?php

class MovieViewerDealPackPaygentTradingIdGenerator {
    private $offer;
    private $user;
    
    function __construct($user, $offer) {
        $this->offer = $offer;
        $this->user = $user;
    }
    
    public function generate() {
        if (!$this->user->hasMemberId()) {
            throw new MovieViewerNoMemberIdException();
        }
        
        $course_id_short = $this->offer->getCourseIdShort();
        $pack_number = sprintf('%02d', $this->offer->getPackNumber());
        
        $price_class = "F";
        if ($this->offer->canDiscount()) {
            $price_class = "D";
        }

        // [コースID(短縮)]S[セット番号]_[D(割引あり)/F(定価)]_[会員番号(-を_に変換)]
        return "{$course_id_short}Set{$pack_number}_{$price_class}_{$this->convertMemberIdForPaygent($this->user->memberId)}";
    }
    
    function convertMemberIdForPaygent($member_id) {
        // ハイフンは利用できないので、アンダーバーに置換する
        return mb_ereg_replace("[¥-]", "_", $member_id);        
    }
}

class MovieViewerDealPackPaygentParameterGenerator extends MovieViewerPaygentParameterGenerator {

    private $offer;

    function __construct($paygent_settings, $user, $offer) {
        $this->offer = $offer;
        parent::__construct(
              $paygent_settings
            , new MovieViewerDealPackPaygentTradingIdGenerator($user, $offer)
        );
    }

    function getId() {
        return "{$this->offer->getPrice()->getTotalAmountWithTax()}";
    }

    function getPaymentDetail() {
        return mb_convert_kana($this->offer->describePackShort(), 'S');        
    }
}

?>