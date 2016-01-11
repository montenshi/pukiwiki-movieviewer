<?php

class MovieViewerNoMemberIdException extends Exception {}

class MovieViewerPaygentParameterGenerator {
    private $paygent_settings;
    private $user;
    private $offer;
    
    function __construct($paygent_settings, $user, $offer) {
        $this->paygent_settings = $paygent_settings;
        $this->user = $user;
        $this->offer = $offer;        
    }
    
    public function getTradingId() {
        if (!$this->user->hasMemberId()) {
            throw new MovieViewerNoMemberIdException();
        }
        
        $course_id_short = $this->offer->getCourseIdShort();
        $pack_number = sprintf('%02d', $this->offer->getPackNumber());

        return "{$course_id_short}s{$pack_number}_" . mb_ereg_replace("[¥-]", "_", $this->user->memberId);
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

?>