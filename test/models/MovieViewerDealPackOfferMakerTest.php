<?php

require_once('MovieViewerTestCase.php');

class MovieViewerDealPackOfferMakerTest extends MovieViewerTestCase {

    public function setUp() {
        parent::setUp();

        $settings = new MovieViewerSettings();
        $settings->data["dir"] = dirname(__FILE__) . "/../resources";
        $settings->timezone = new DateTimeZone("Asia/Tokyo");
        $this->setGlobalSettings($settings);
    }

    public function testGetOfferShouldReturnsNoOfferBeforeBegining() {
        // 視聴期限開始1秒まえ
        $date_freeze = new DateTime("2015-08-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertEquals(NULL, $offer);
    }

    public function testGetOfferShouldReturnsNoOfferRemainsMoreThan1Month() {
        // 視聴期限切れ1ヶ月と1秒まえ
        $date_freeze = new DateTime("2015-10-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertEquals(NULL, $offer);
    }

    public function testGetOfferShouldReturnsOfferAboutToExpire() {
        // 視聴期限切れ1ヶ月
        $date_freeze = new DateTime("2015-10-15 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertEquals("K1Kiso-2", $offer->getPackId());
        $this->assertTrue($offer->canDiscount());
    }

    public function testGetOfferShouldReturnsOfferAlreadyExpired() {
        // 11-15で視聴期限が切れる
        $date_freeze = new DateTime("2015-11-15 00:00:01+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertEquals("K1Kiso-2", $offer->getPackId());
        $this->assertFalse($offer->canDiscount());
    }

    public function testGetOffersShouldReturnsNoOfferRemainsMoreThan1Month() {
        // 視聴期限切れ1ヶ月と1秒まえ
        $date_freeze = new DateTime("2015-10-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso", "K2Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offers = $maker->getOffers();

        $this->assertCount(1, $offers);
        $this->assertEquals("K2Kiso-1", $offers[0]->getPackId());
    }

    public function testGetOffersShouldReturnsOfferAboutToExpire() {
        // 視聴期限切れ1ヶ月
        $date_freeze = new DateTime("2015-10-15 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso", "K2Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offers = $maker->getOffers();

        $this->assertCount(2, $offers);
        $this->assertEquals("K1Kiso-2", $offers[0]->getPackId());
        $this->assertEquals("K2Kiso-1", $offers[1]->getPackId());
    }

    public function testGetOffersShouldReturnsOfferFirstTime() {

        $date_freeze = new DateTime("2015-10-15 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "bbb@bbb.ccc";
        $user->selected_courses = array("K1Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offers = $maker->getOffers();

        $this->assertCount(1, $offers);
        $this->assertEquals("K1Kiso-1", $offers[0]->getPackId());
        $this->assertEquals("2015-10-15 00:00:00+09:00", $offers[0]->getDiscountPeriod()->date_begin->format("Y-m-d H:i:sP"));
        $this->assertEquals("2015-10-31 23:59:59+09:00", $offers[0]->getDiscountPeriod()->date_end->format("Y-m-d H:i:sP"));
        $this->assertTrue($offers[0]->canDiscount());
    }
}

?>