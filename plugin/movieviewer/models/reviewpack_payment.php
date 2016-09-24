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

class MovieViewerReviewPackPaygentTradingIdGenerator
{
    private $_request;
    private $_user;
    
    function __construct($user, $request)
    {
        $this->_request = $request;
        $this->_user = $user;
    }
    
    function generate()
    {
        if (!$this->_user->hasMemberId()) {
            throw new MovieViewerNoMemberIdException();
        }

        $date_requested = $this->_request->getDateRequested();
        $mdhns = base_convert($date_requested->format("mdHis"), 10, 32);
        $formatted_date = "{$date_requested->format('y')}{$mdhns}";
        
        // [コースID(短縮)]S[セット番号]_[D(割引あり)/F(定価)]_[会員番号(-を_に変換)]
        return "R_{$this->convertMemberIdForPaygent($this->_user->memberId)}_{$formatted_date}";
    }
    
    private function convertMemberIdForPaygent($member_id)
    {
        // ハイフンは利用できないので、アンダーバーに置換する
        return mb_ereg_replace("[¥-]", "_", $member_id);        
    }
}

class MovieViewerReviewPackPaygentParameterGenerator extends MovieViewerPaygentParameterGenerator
{
    private $_request;

    function __construct($paygent_settings, $user, $request)
    {
        $this->_request = $request;
        parent::__construct(
            $paygent_settings,
            new MovieViewerReviewPackPaygentTradingIdGenerator($user, $request)
        );
    }

    function getId()
    {
        return "{$this->_request->getPrice()->getTotalAmountWithTax()}";
    }

    function getPaymentDetail()
    {
        return mb_convert_kana($this->_request->describePackShort(), "AS");
    }
}

?>