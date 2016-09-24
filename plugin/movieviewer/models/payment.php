<?php

/**
 * Pukiwikiプラグイン::動画視聴 支払い
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.Payment
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerNoMemberIdException extends Exception
{
}

abstract class MovieViewerPaygentParameterGenerator {
    private $_paygent_settings;
    private $_trading_id_generator;
    
    abstract function getId();
    abstract function getPaymentDetail();

    function __construct($paygent_settings, $trading_id_generator) {
        $this->_paygent_settings = $paygent_settings;
        $this->_trading_id_generator = $trading_id_generator;
    }
    
    function getTradingId()
    {
        return $this->_trading_id_generator->generate();        
    }
    
    function getSeqMerchantId()
    {
        return $this->_paygent_settings["merchant_id"];
    }
    
    function getHash()
    {
        $org_str = $this->getTradingId() .
                   $this->getId() .
                   $this->getSeqMerchantId() .
                   $this->paygent_settings["hash_key"];
        return hash("sha256", $org_str);
    }
}

?>