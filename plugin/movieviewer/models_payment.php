<?php

class MovieViewerNoMemberIdException extends Exception {}

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

class MovieViewerPaygentParameterGenerator {
    private $paygent_settings;
    private $user;
    private $offer;
    private $trading_id_generator;
    
    function __construct($paygent_settings, $user, $offer, $trading_id_generator) {
        $this->paygent_settings = $paygent_settings;
        $this->user = $user;
        $this->offer = $offer;
        $this->trading_id_generator = $trading_id_generator;
    }
    
    public function getTradingId() {
        return $this->trading_id_generator->generate();        
    }
    
    public function getSeqMerchantId() {
        return $this->paygent_settings["merchant_id"];
    }
    
    public function getId() {
        return "{$this->offer->getPrice()->getTotalAmountWithTax()}";
    }
    
    public function getHash() {
        $org_str = $this->getTradingId() .
                   $this->getId() .
                   $this->getSeqMerchantId() .
                   $this->paygent_settings["hash_key"];
        return hash("sha256", $org_str);
    }
    
    public function getPaymentDetail() {
        return mb_convert_kana($this->offer->describePackShort(), 'S');        
    }
}

class MovieViewerDealPackPaygentParameterGenerator extends MovieViewerPaygentParameterGenerator {
    function __construct($paygent_settings, $user, $offer) {
        parent::__construct(
              $paygent_settings
            , $user
            , $offer
            , new MovieViewerDealPackPaygentTradingIdGenerator($user, $offer)
        );
    }
}

?>