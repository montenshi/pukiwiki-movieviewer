<?php

require_once('MovieViewerTestCase.php');

class MovieViewerDealPackOfferMakerTest extends MovieViewerTestCase {

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
        // 視聴期限切れ前月1日の１秒前
        $date_freeze = new DateTime("2015-09-30 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertEquals(NULL, $offer);
    }

    public function testGetOfferShouldReturnsDiscountOfferFirstDayOfLastMonth() {
        // 視聴期限切れ前月1日
        $date_freeze = new DateTime("2015-10-01 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertTrue($offer != NULL);
        $this->assertEquals("K1Kiso-2", $offer->getPackId());
        $this->assertTrue($offer->canDiscount());
        $this->assertEquals(new DateTime("2015-10-31 23:59:59+09:00"), $offer->getPaymentGuide()->deadline);
    }

    public function testGetOfferShouldReturnsDiscountOfferLastDayOfLastMonth() {
        // 視聴期限切れ前月末日
        $date_freeze = new DateTime("2015-10-31 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertTrue($offer != NULL);
        $this->assertEquals("K1Kiso-2", $offer->getPackId());
        $this->assertTrue($offer->canDiscount());
        $this->assertEquals(new DateTime("2015-10-31 23:59:59+09:00"), $offer->getPaymentGuide()->deadline);
    }

    public function testGetOfferShouldReturnsOfferFirstOfSameMonth() {
        // 視聴期限切れ当月1日
        $date_freeze = new DateTime("2015-11-01 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso");

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertTrue($offer != NULL);
        $this->assertEquals("K1Kiso-2", $offer->getPackId());
        $this->assertFalse($offer->canDiscount());
        $this->assertEquals(new DateTime("2015-11-30 23:59:59+09:00"), $offer->getPaymentGuide()->deadline);
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

        $this->assertTrue($offer != NULL);
        $this->assertEquals("K1Kiso-2", $offer->getPackId());
        $this->assertFalse($offer->canDiscount());
        $this->assertEquals(new DateTime("2015-11-30 23:59:59+09:00"), $offer->getPaymentGuide()->deadline);
    }

    public function testGetOffersShouldReturnsOneOfferRemainsMoreThan1Month() {
        // 視聴期限切れ前月1日１秒前
        $date_freeze = new DateTime("2015-09-30 23:59:59+09:00");
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

    public function testGetOffersShouldReturnsTwoOfferAboutToExpire() {
        // 視聴期限切れ前月1日
        $date_freeze = new DateTime("2015-10-01 00:00:00+09:00");
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