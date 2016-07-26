<?php

require_once('MovieViewerTestCase.php');

class MovieViewerDealPackPaymentConfirmationRepositoryInFileTest extends MovieViewerTestCase {

    public function setUp() {
        parent::setUp();

        $settings = new MovieViewerSettings();
        $settings->data["dir"] = "test/models/resources";
        $settings->payment = new MovieViewerPaymentSettings(
            Array("bank_transfer" => 
            Array("bank_names" => Array(), 
                  "bank_accounts"=>Array(), 
                  "notes" => ""))
        );
        $settings->timezone = new DateTimeZone("Asia/Tokyo");
        $this->setGlobalSettings($settings);
    }

    public function testFindValidsByCourseReturnsNoneWhenBeforeStart() {
        // 視聴期限開始1秒まえ
        $date_freeze = new DateTime("2015-08-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $settings = plugin_movieviewer_get_global_settings();
        $repo = new MovieViewerDealPackPaymentConfirmationRepositoryInFile($settings);

        $objects = $repo->findValidsByCourse("aaa@bbb.ccc");

        $this->assertEquals(0, count($objects));
    }

    public function testFindValidsByCourseReturnsNoneWhenAfterEnd() {
        // 視聴期限切れちょうど
        $date_freeze = new DateTime("2015-11-15 00:00:01+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $settings = plugin_movieviewer_get_global_settings();
        $repo = new MovieViewerDealPackPaymentConfirmationRepositoryInFile($settings);

        $objects = $repo->findValidsByCourse("aaa@bbb.ccc");

        $this->assertEquals(0, count($objects));
    }

    public function testFindValidsByCourseReturnsValidConfirmations_One() {
        // 視聴期限ぴったり
        $date_freeze = new DateTime("2015-08-15 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $settings = plugin_movieviewer_get_global_settings();
        $repo = new MovieViewerDealPackPaymentConfirmationRepositoryInFile($settings);

        $objects = $repo->findValidsByCourse("aaa@bbb.ccc");

        $this->assertEquals(1, count($objects));
        $this->assertEquals("K1Kiso-1", $objects[0]->getPack()->getId());
    }

    public function testFindValidsByCourseReturnsValidConfirmations_Two() {
        // 視聴期限ぴったり
        $date_freeze = new DateTime("2015-08-15 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $settings = plugin_movieviewer_get_global_settings();
        $repo = new MovieViewerDealPackPaymentConfirmationRepositoryInFile($settings);

        $objects = $repo->findValidsByCourse("ccc@bbb.ccc");

        $this->assertEquals(2, count($objects));
        $this->assertEquals("K1Kiso-1", $objects[0]->getPack()->getId());
        $this->assertEquals("K2Kiso-1", $objects[1]->getPack()->getId());
    }

}

?>