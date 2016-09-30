<?php

require_once 'MovieViewerTestCase.php';

class MovieViewerReviewPackPaymentConfirmationRepositoryInFileTest extends MovieViewerTestCase
{
    function setUp()
    {
        parent::setUp();

        $settings = new MovieViewerSettings();
        $settings->data["dir"] = "test/models/resources";
        $settings->timezone = new DateTimeZone("Asia/Tokyo");
        $this->setGlobalSettings($settings);
    }

    function testStoreSaveToFile()
    {
        $date_freeze = new DateTime("2015-08-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $settings = plugin_movieviewer_get_global_settings();
        $repo = new MovieViewerReviewPackPaymentConfirmationRepositoryInFile($settings);

        $request = new MovieViewerReviewPackPurchaseRequest("aaa@bbb.ccc", "credit", array("K1Kiso_01"));
        $object = MovieViewerReviewPackPaymentConfirmation::createFromRequest($request, null);

        $repo->store($object);

        $object = $repo->findBy("aaa@bbb.ccc", "20150814235959+0900");
        
        $this->assertEquals("aaa@bbb.ccc", $object->user_id);
        $this->assertEquals("credit", $object->purchase_method);

        $items = $object->getItems();
        $this->assertEquals(1, count($items));
        $this->assertEquals("K1Kiso", $items[0]->course_id);
        $this->assertEquals("01", $items[0]->session_id);
        $viewing_period = $object->getViewingPeriod();
        $this->assertEquals(new DateTime("2015-08-16 00:00:00+09:00"), $viewing_period->date_begin);
        $this->assertEquals(new DateTime("2015-09-15 23:59:59+09:00"), $viewing_period->date_end);
    }
}
?>