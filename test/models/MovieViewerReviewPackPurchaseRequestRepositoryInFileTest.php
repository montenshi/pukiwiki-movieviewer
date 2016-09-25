<?php

require_once('MovieViewerTestCase.php');

class MovieViewerReviewPackPurchaseRequestRepositoryInFileTest extends MovieViewerTestCase {

    public function setUp() {
        parent::setUp();

        $settings = new MovieViewerSettings();
        $settings->data["dir"] = "test/models/resources";
        $settings->timezone = new DateTimeZone("Asia/Tokyo");
        $this->setGlobalSettings($settings);
    }

    public function testStoreSaveToFile() {
        $date_freeze = new DateTime("2015-08-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $settings = plugin_movieviewer_get_global_settings();
        $repo = new MovieViewerReviewPackPurchaseRequestRepositoryInFile($settings);

        $object = new MovieViewerReviewPackPurchaseRequest("aaa@bbb.ccc", "credit", array("K1Kiso_01"));
        $repo->store($object);

        $object = $repo->findBy("aaa@bbb.ccc", "20150814235959+0900");
        
        $this->assertEquals("aaa@bbb.ccc", $object->user_id);
        $this->assertEquals("credit", $object->purchase_method);
        $this->assertEquals(new DateTime("2015-08-14 23:59:59+09:00"), $object->getDateRequested());

        $items = $object->getItems();
        $this->assertEquals(1, count($items));
        $this->assertEquals("K1Kiso", $items[0]->course_id);
        $this->assertEquals("01", $items[0]->session_id);
    }

    public function testStashSaveToTempFileAndReturnStashID() {
        $date_freeze = new DateTime("2015-08-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $settings = plugin_movieviewer_get_global_settings();
        $repo = new MovieViewerReviewPackPurchaseRequestRepositoryInFile($settings);

        $object = new MovieViewerReviewPackPurchaseRequest("aaa@bbb.ccc", "credit", array("K1Kiso_01"));
        $stash_id = $repo->stash($object);

        $this->assertTrue($stash_id !== '');
        $this->assertTrue(file_exists("test/models/resources/purchase/review_pack/_stash/{$stash_id}.yml"));
    }

    public function testRestoreDeserializeObjectAndDeleteTempFile() {
        $date_freeze = new DateTime("2015-08-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        $settings = plugin_movieviewer_get_global_settings();
        $repo = new MovieViewerReviewPackPurchaseRequestRepositoryInFile($settings);

        $object = new MovieViewerReviewPackPurchaseRequest("aaa@bbb.ccc", "credit", array("K1Kiso_01"));
        $stash_id = $repo->stash($object);

        $object = $repo->restore($stash_id);
        
        $this->assertEquals("aaa@bbb.ccc", $object->user_id);
        $this->assertFalse(file_exists("test/models/resources/purchase/review_pack/_stash/{$stash_id}.yml"));
    }

    public function testFindNotYetConfirmedReturnsObjects() {
        $date_freeze = new DateTime("2015-08-14 23:59:59+09:00");
        timecop_freeze($date_freeze->getTimestamp());

        // 常にファイルの状態を同じにしたいなので、data dir を専用のものにする
        $settings = new MovieViewerSettings();
        $settings->data["dir"] = "test/models/resources_not_yet_confirmed";
        $settings->timezone = new DateTimeZone("Asia/Tokyo");

        $repo = new MovieViewerReviewPackPurchaseRequestRepositoryInFile($settings);

        $objects = $repo->findNotYetConfirmed();

        $this->assertEquals(1, count($objects));

        $object = $objects[0];
        $this->assertEquals("zzz@bbb.ccc", $object->user_id);
    }
}

?>