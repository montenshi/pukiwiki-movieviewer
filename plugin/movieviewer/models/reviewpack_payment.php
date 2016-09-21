<?php

class MovieViewerReviewPackPaygentTradingIdGenerator {
    private $request;
    private $user;
    
    function __construct($user, $request) {
        $this->request = $request;
        $this->user = $user;
    }
    
    public function generate() {
        if (!$this->user->hasMemberId()) {
            throw new MovieViewerNoMemberIdException();
        }

        $date_requested = $this->request->getDateRequested();
        $mdhns = base_convert($date_requested->format("mdHis"), 10, 32);
        $formatted_date = "{$date_requested->format('y')}{$mdhns}";
        
        // [コースID(短縮)]S[セット番号]_[D(割引あり)/F(定価)]_[会員番号(-を_に変換)]
        return "R_{$this->convertMemberIdForPaygent($this->user->memberId)}_{$formatted_date}";
    }
    
    function convertMemberIdForPaygent($member_id) {
        // ハイフンは利用できないので、アンダーバーに置換する
        return mb_ereg_replace("[¥-]", "_", $member_id);        
    }
}

class MovieViewerReviewPackPaygentParameterGenerator extends MovieViewerPaygentParameterGenerator {

    private $request;

    function __construct($paygent_settings, $user, $request) {
        $this->request = $request;
        parent::__construct(
              $paygent_settings
            , new MovieViewerReviewPackPaygentTradingIdGenerator($user, $request)
        );
    }

    function getId() {
        return "{$this->request->getPrice()->getTotalAmountWithTax()}";
    }

    function getPaymentDetail() {
        return mb_convert_kana($this->request->describePackShort(), "AS");
    }
}

?>