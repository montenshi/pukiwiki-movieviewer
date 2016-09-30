<?php

require_once 'MovieViewerTestCase.php';

class MovieViewerDealPackOfferMakerTest extends MovieViewerTestCase
{
    function setUp()
    {
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

    function testGetOfferShouldReturnsNoOfferBeforeBegining()
    {
        // 視聴期限開始1秒まえ
        $date_freeze = new DateTime("2015-08-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertEquals(null, $offer);
    }

    function testGetOfferShouldReturnsNoOfferRemainsMoreThan1Month()
    {
        // 視聴期限切れ前月1日の１秒前
        $date_freeze = new DateTime("2015-09-30 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertEquals(null, $offer);
    }

    function testGetOfferShouldReturnsDiscountOfferFirstDayOfLastMonth()
    {
        // 視聴期限切れ前月1日
        $date_freeze = new DateTime("2015-10-01 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertTrue($offer != null);
        $this->assertEquals("K1Kiso-2", $offer->getPackId());
        $this->assertTrue($offer->canDiscount());
        $this->assertEquals(new DateTime("2015-10-31 23:59:59+09:00"), $offer->getPaymentGuide()->deadline);
    }

    function testGetOfferShouldReturnsDiscountOfferLastDayOfLastMonth()
    {
        // 視聴期限切れ前月末日
        $date_freeze = new DateTime("2015-10-31 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertTrue($offer != null);
        $this->assertEquals("K1Kiso-2", $offer->getPackId());
        $this->assertTrue($offer->canDiscount());
        $this->assertEquals(new DateTime("2015-10-31 23:59:59+09:00"), $offer->getPaymentGuide()->deadline);
    }

    function testGetOfferShouldReturnsOfferFirstOfSameMonth()
    {
        // 視聴期限切れ当月1日
        $date_freeze = new DateTime("2015-11-01 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertTrue($offer != null);
        $this->assertEquals("K1Kiso-2", $offer->getPackId());
        $this->assertFalse($offer->canDiscount());
        $this->assertEquals(new DateTime("2015-11-30 23:59:59+09:00"), $offer->getPaymentGuide()->deadline);
    }

    function testGetOfferShouldReturnsOfferAlreadyExpired()
    {
        // 11-15で視聴期限が切れる
        $date_freeze = new DateTime("2015-11-15 00:00:01+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offer = $maker->getOffer();

        $this->assertTrue($offer != null);
        $this->assertEquals("K1Kiso-2", $offer->getPackId());
        $this->assertFalse($offer->canDiscount());
        $this->assertEquals(new DateTime("2015-11-30 23:59:59+09:00"), $offer->getPaymentGuide()->deadline);
    }

    function testGetOffersShouldReturnsOneOfferRemainsMoreThan1Month()
    {
        // 視聴期限切れ前月1日１秒前
        $date_freeze = new DateTime("2015-09-30 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_routes = array(
            new MovieViewerCourseRoute(array("K1Kiso")),
            new MovieViewerCourseRoute(array("K2Kiso"))
        );

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offers = $maker->getOffers();

        $this->assertCount(1, $offers);
        $this->assertEquals("K2Kiso-1", $offers[0]->getPackId());
    }

    function testGetOffersShouldReturnsTwoOfferAboutToExpire()
    {
        // 視聴期限切れ前月1日
        $date_freeze = new DateTime("2015-10-01 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_routes = array(
            new MovieViewerCourseRoute(array("K1Kiso")),
            new MovieViewerCourseRoute(array("K2Kiso"))
        );

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offers = $maker->getOffers();

        $this->assertCount(2, $offers);
        $this->assertEquals("K1Kiso-2", $offers[0]->getPackId());
        $this->assertEquals("K2Kiso-1", $offers[1]->getPackId());
    }

    function testGetOffersShouldReturnsOfferFirstTime()
    {
        $date_freeze = new DateTime("2015-10-15 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "bbb@bbb.ccc";

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offers = $maker->getOffers();

        $this->assertCount(1, $offers);
        $this->assertEquals("K1Kiso-1", $offers[0]->getPackId());
        $this->assertEquals("2015-10-15 00:00:00+09:00", $offers[0]->getDiscountPeriod()->date_begin->format("Y-m-d H:i:sP"));
        $this->assertEquals("2015-10-31 23:59:59+09:00", $offers[0]->getDiscountPeriod()->date_end->format("Y-m-d H:i:sP"));
        $this->assertTrue($offers[0]->canDiscount());
    }

    function testGetOffersShouldReturnNextCourseOffer()
    {
        $date_freeze = new DateTime("2015-10-15 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "ddd@bbb.ccc";

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offers = $maker->getOffers();

        $this->assertCount(1, $offers);
        $this->assertEquals("K2Kiso-1", $offers[0]->getPackId());
    }

    function testGetOffersShouldReturnEmptyWhenLastCourseEnded()
    {
        $date_freeze = new DateTime("2015-10-15 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "ddd@bbb.ccc";
        $user->selected_routes = array(
            new MovieViewerCourseRoute(array("K1Kiso")),
        );

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offers = $maker->getOffers();

        $this->assertCount(0, $offers);
    }

    function testGetOffersShouldReturnNextCourseOfferWhenFirstPackEndedBothRoutes()
    {
        $date_freeze = new DateTime("2015-10-15 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "eee@bbb.ccc";
        $user->selected_routes = array(
            new MovieViewerCourseRoute(array("K1Kiso","OABunka")),
            new MovieViewerCourseRoute(array("K2Kiso"))
        );

        $settings = plugin_movieviewer_get_global_settings();
        $maker = new MovieViewerDealPackOfferMaker($settings->payment, $user);
        $offers = $maker->getOffers();

        $this->assertCount(1, $offers);
        $this->assertEquals("OABunka-1", $offers[0]->getPackId());
    }
}

?>