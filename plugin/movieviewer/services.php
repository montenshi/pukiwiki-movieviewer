<?php

/**
 * Pukiwikiプラグイン::動画視聴 サービス(一連の業務処理をまとめたもの)
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Services
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerReviewPackPurchaseRequestService
{
    private $_settings;

    function __construct($settings)
    {
        $this->_settings = $settings;
    }

    function doRequest($user, $request_stash_id)
    {
        $request = $this->createRequest($request_stash_id);

        $this->sendBankTransferInformation($user, $request);

        $this->sendRequestNotifycation($user, $request);

        return $request;
    }

    private function createRequest($request_stash_id)
    {
        $repo = plugin_movieviewer_get_review_pack_purchase_request_repository();

        $request = null;
        try {
            $request = $repo->restore($request_stash_id);
        } catch (Exception $ex) {
            throw new Exception("指定した内容に誤りがあります。");
        }

        try {
            $repo->store($request);
        } catch (Exception $ex) {
            throw new Exception("データの保存に失敗しました。");
        }

        return $request;
    }

    private function sendBankTransferInformation($user, $request)
    {
        $payment_guide = MovieViewerReviewPackPurchasePaymentGuide::create($this->_settings->payment, $request);
        $price_with_notes = plugin_movieviewer_render_price_with_notes($request->getPrice(), "回", true);
        $item_names = $this->getItemNames($request);

        $mail_builder = new MovieViewerReviewPackBankTransferInformationMailBuilder($this->_settings->mail);
        $mail = $mail_builder->build($user, $item_names, $price_with_notes, $payment_guide->bank_transfer, $payment_guide->deadline);
        $result = $mail->send();

        if (!$result) {
            MovieViewerLogger::getLogger()->addError(
                "再視聴案内通知エラー", array("error_statement"=>$mail->ErrorInfo)
            );

            throw new Exception("メールの送信に失敗しました。");
        }
    }

    private function sendRequestNotifycation($user, $request)
    {
        $payment_guide = MovieViewerReviewPackPurchasePaymentGuide::create($this->_settings->payment, $request);
        $price_with_notes = plugin_movieviewer_render_price_with_notes($request->getPrice(), "回", true);
        $item_names = $this->getItemNames($request);

        $mail_builder = new MovieViewerReviewPackRequestNotificationMailBuilder($this->_settings->mail);
        $mail = $mail_builder->build($user, $item_names, $price_with_notes, $payment_guide->bank_transfer, $payment_guide->deadline);
        $result = $mail->send();

        if (!$result) {
            MovieViewerLogger::getLogger()->addError(
                "再視聴申し込み通知エラー", array("error_statement"=>$mail->ErrorInfo)
            );

            // スタッフ向けはログのみで終わらせる
        }
    }

    private function getItemNames($request)
    {
        $item_names = array();
        $courses = plugin_movieviewer_get_courses_repository()->find();
        foreach ($request->getItemsByCourse() as $course_id => $items) {
            $course = $courses->getCourse($course_id);
            foreach ($items as $item) {
                $session = $course->getSession($item->session_id);
                $item_names[] = "{$course->describe()} {$session->describe()}";
            }
        }
        return $item_names;
    }

}

class MovieViewerReviewPackPurchaseConfirmationService
{
    private $_settings;

    function __construct($settings)
    {
        $this->_settings = $settings;
    }

    function preConfirm($request, $date_begin)
    {
        return MovieViewerReviewPackPaymentConfirmation::createFromRequest($request, $date_begin);
    }

    function confirm($request, $date_begin)
    {
        $confirmation = MovieViewerReviewPackPaymentConfirmation::createFromRequest($request, $date_begin);

        $periods = $this->addViewingPeriods($confirmation);

        plugin_movieviewer_get_viewing_periods_by_user_repository()->store($periods);
        plugin_movieviewer_get_review_pack_payment_confirmation_repository()->store($confirmation);

        return $confirmation;
    }

    private function addViewingPeriods($confirmation)
    {
        $periods = plugin_movieviewer_get_viewing_periods_by_user_repository()->findById($confirmation->getUser()->id);

        $date_begin = $confirmation->getViewingPeriod()->date_begin;
        $date_end = $confirmation->getViewingPeriod()->date_end;

        foreach ($confirmation->getPack()->getItems() as $item) {
            $periods->addPeriod($item->course_id, $item->session_id, $date_begin, $date_end);
        }

        return $periods;
    }
}

?>