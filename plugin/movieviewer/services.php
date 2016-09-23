<?php

class MovieViewerReviewPackPurchaseRequestService {

    private $settings;

    function __construct($settings) {
        $this->settings = $settings;
    }

    function doRequest($user, $request_stash_id) {

        $request = $this->createRequest($request_stash_id);

        $this->sendBankTransferInformation($user, $request);

        $this->sendRequestNotifycation($user, $request);

        return $request;
    }

    private function createRequest($request_stash_id) {

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

    private function sendBankTransferInformation($user, $request) {

        $payment_guide = MovieViewerReviewPackPurchasePaymentGuide::create($this->settings->payment, $request);
        $price_with_notes = plugin_movieviewer_render_price_with_notes($request->getPrice(), "回", TRUE);
        $item_names = $this->getItemNames($request);

        $mail_builder = new MovieViewerReviewPackBankTransferInformationMailBuilder($this->settings->mail);
        $mail = $mail_builder->build($user, $item_names, $price_with_notes, $payment_guide->bank_transfer, $payment_guide->deadline);
        $result = $mail->send();

        if (!$result) {
            MovieViewerLogger::getLogger()->addError(
                "再視聴案内通知エラー", array("error_statement"=>$mail->ErrorInfo)
            );

            throw new Exception("メールの送信に失敗しました。");
        }
    }

    private function sendRequestNotifycation($user, $request) {

        $payment_guide = MovieViewerReviewPackPurchasePaymentGuide::create($this->settings->payment, $request);
        $price_with_notes = plugin_movieviewer_render_price_with_notes($request->getPrice(), "回", TRUE);
        $item_names = $this->getItemNames($request);

        $mail_builder = new MovieViewerReviewPackRequestNotificationMailBuilder($this->settings->mail);
        $mail = $mail_builder->build($user, $item_names, $price_with_notes, $payment_guide->bank_transfer, $payment_guide->deadline);
        $result = $mail->send();

        if (!$result) {
            MovieViewerLogger::getLogger()->addError(
                "再視聴申し込み通知エラー", array("error_statement"=>$mail->ErrorInfo)
            );

            // スタッフ向けはログのみで終わらせる
        }
    }

    private function getItemNames($request) {
        $item_names = array();
        $courses = plugin_movieviewer_get_courses_repository()->find();
        foreach($request->getItemsByCourse() as $course_id => $items) {
            $course = $courses->getCourse($course_id);
            foreach($items as $item) {
                $session = $course->getSession($item->session_id);
                $item_names[] = "{$course->describe()} {$session->describe()}";
            }
        }
        return $item_names;
    }

}

?>