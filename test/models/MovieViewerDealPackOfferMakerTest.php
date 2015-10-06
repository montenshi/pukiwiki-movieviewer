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

    public function testGetOfferNoOffer() {
        // 視聴期限切れ1ヶ月と1秒まえ
        $date_freeze = new DateTime("2015-10-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso","K2Kiso");
        $maker = new MovieViewerDealPackOfferMaker($user);
        $offers = $maker->getOffers();

        $this->assertEquals(1, count($offers));
        $this->assertEquals("K2Kiso-1", $offers[0]->getPackId());
    }

    public function testGetOfferAboutToExpire() {
        // 視聴期限切れ1ヶ月
        $date_freeze = new DateTime("2015-10-15 00:00:00+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso","K2Kiso");
        $maker = new MovieViewerDealPackOfferMaker($user);
        $offers = $maker->getOffers();

        $this->assertEquals(2, count($offers));
        $this->assertEquals("K1Kiso-2", $offers[0]->getPackId());
        $this->assertEquals("K2Kiso-1", $offers[1]->getPackId());
    }

    public function testGetOfferAlreadyExpired() {
        // 11-15で視聴期限が切れる
        $date_freeze = new DateTime("2015-11-15 00:00:01+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $user = new MovieViewerUser();
        $user->id = "aaa@bbb.ccc";
        $user->selected_courses = array("K1Kiso","K2Kiso");
        $maker = new MovieViewerDealPackOfferMaker($user);
        $offers = $maker->getOffers();

        $this->assertEquals(2, count($offers));
        $this->assertEquals("K1Kiso-2", $offers[0]->getPackId());
        $this->assertEquals("K2Kiso-1", $offers[1]->getPackId());
    }

    function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

}

?>