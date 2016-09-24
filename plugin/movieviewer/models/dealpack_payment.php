<?php

/**
 * Pukiwikiプラグイン::動画視聴 受講申し込み入金確認
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

class MovieViewerDealPackPaygentTradingIdGenerator
{
    private $_offer;
    private $_user;
    
    function __construct($user, $offer)
    {
        $this->_offer = $offer;
        $this->_user = $user;
    }
    
    function generate()
    {
        if (!$this->_user->hasMemberId()) {
            throw new MovieViewerNoMemberIdException();
        }
        
        $course_id_short = $this->_offer->getCourseIdShort();
        $pack_number = sprintf('%02d', $this->_offer->getPackNumber());
        
        $price_class = "F";
        if ($this->_offer->canDiscount()) {
            $price_class = "D";
        }

        // [コースID(短縮)]S[セット番号]_[D(割引あり)/F(定価)]_[会員番号(-を_に変換)]
        return "{$course_id_short}Set{$pack_number}_{$price_class}_{$this->convertMemberIdForPaygent($this->_user->memberId)}";
    }
    
    function convertMemberIdForPaygent($member_id)
    {
        // ハイフンは利用できないので、アンダーバーに置換する
        return mb_ereg_replace("[¥-]", "_", $member_id);        
    }
}

class MovieViewerDealPackPaygentParameterGenerator extends MovieViewerPaygentParameterGenerator
{

    private $_offer;

    function __construct($paygent_settings, $user, $offer)
    {
        $this->_offer = $offer;
        parent::__construct(
            $paygent_settings,
            new MovieViewerDealPackPaygentTradingIdGenerator($user, $offer)
        );
    }

    function getId()
    {
        return "{$this->_offer->getPrice()->getTotalAmountWithTax()}";
    }

    function getPaymentDetail()
    {
        return mb_convert_kana($this->_offer->describePackShort(), 'S');        
    }
}

?>