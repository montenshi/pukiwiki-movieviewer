<?php

require_once('MovieViewerTestCase.php');

class MovieViewerReviewPackTest extends MovieViewerTestCase {

    public function setUp() {
        parent::setUp();

        $settings = new MovieViewerSettings();
        $settings->data["dir"] = dirname(__FILE__) . "/resources";
        $settings->timezone = new DateTimeZone("Asia/Tokyo");
        $this->setGlobalSettings($settings);
    }

    public function testConstructorCreatesReviewItems() {
        $sessions_string = "K1Kiso_01,K2Kiso_02,K2Kiso_06,K2Kiso_08";
        $object = new MovieViewerReviewPack($sessions_string);

        $items = $object->getItems();
        $this->assertEquals(4, count($items));

        $item = $items[0];
        $this->assertEquals("K1Kiso", $item->course_id);
        $this->assertEquals("01", $item->session_id);
    }

    public function testGetItemsByCourseReturnsSortedObjectsByCourse() {
        $sessions_string = "K1Kiso_01,K2Kiso_02,K2Kiso_06,K2Kiso_08";
        $object = new MovieViewerReviewPack($sessions_string);

        $courses = $object->getItemsByCourse();
        $this->assertEquals(2, count($courses));
        
        $items = $courses['K1Kiso'];
        $this->assertEquals(1, count($items));
        $this->assertEquals("K1Kiso", $items[0]->course_id);
        $this->assertEquals("01", $items[0]->session_id);

        $items = $courses['K2Kiso'];
        $this->assertEquals(3, count($items));
        $this->assertEquals("K2Kiso", $items[2]->course_id);
        $this->assertEquals("08", $items[2]->session_id);
    }

    public function testGetPriceReturnsPackPrice() {
        $sessions_string = "K1Kiso_01,K2Kiso_02,K2Kiso_06,K2Kiso_08";
        $object = new MovieViewerReviewPack($sessions_string);

        $price = $object->getPrice();
        $this->assertEquals(500 * 4, $price->getTotalAmountWithoutTax());
        $this->assertEquals(540 * 4, $price->getTotalAmountWithTax());        
    }
}

?>