<?php

class MovieViewerNoMemberIdException extends Exception {}

abstract class MovieViewerPaygentParameterGenerator {
    private $paygent_settings;
    private $trading_id_generator;
    
    abstract function getId();
    abstract function getPaymentDetail();

    function __construct($paygent_settings, $trading_id_generator) {
        $this->paygent_settings = $paygent_settings;
        $this->trading_id_generator = $trading_id_generator;
    }
    
    function getTradingId() {
        return $this->trading_id_generator->generate();        
    }
    
    function getSeqMerchantId() {
        return $this->paygent_settings["merchant_id"];
    }
    
    function getHash() {
        $org_str = $this->getTradingId() .
                   $this->getId() .
                   $this->getSeqMerchantId() .
                   $this->paygent_settings["hash_key"];
        return hash("sha256", $org_str);
    }
}

?>