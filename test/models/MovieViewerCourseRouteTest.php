<?php

require_once 'MovieViewerTestCase.php';

class MovieViewerCourseRouteTest extends MovieViewerTestCase
{
    function setUp()
    {
        parent::setUp();

        $settings = new MovieViewerSettings();
        $settings->data["dir"] = "test/models/resources";
        $settings->timezone = new DateTimeZone("Asia/Tokyo");
        $this->setGlobalSettings($settings);
    }

    function testGetNextReturnsCourseId()
    {
        $object = new MovieViewerCourseRoute(array("K1Kiso", "K2Kiso", "OABunka"));

        $this->assertEquals("K2Kiso", $object->getNext("K1Kiso"));
        $this->assertEquals("OABunka", $object->getNext("K2Kiso"));
    }

    function testGetNextReturnsNullWhenLastCourseIdSpecified()
    {
        $object = new MovieViewerCourseRoute(array("K1Kiso", "K2Kiso", "OABunka"));

        $this->assertEquals(null, $object->getNext("OABunka"));
    }

    function testGetNextReturnsNullWhenInvalidCourseIdSpecified()
    {
        $object = new MovieViewerCourseRoute(array("K1Kiso", "K2Kiso", "OABunka"));

        $this->assertEquals(null, $object->getNext("AA"));
    }
}

?>