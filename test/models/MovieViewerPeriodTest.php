<?php

require_once 'MovieViewerTestCase.php';

class MovieViewerPeriodTest extends MovieViewerTestCase
{
    function setUp()
    {
        parent::setUp();

        $settings = new MovieViewerSettings();
        $settings->data["dir"] = dirname(__FILE__) . "/resources";
        $settings->timezone = new DateTimeZone("Asia/Tokyo");
        $this->setGlobalSettings($settings);
    }

    /**
     * @dataProvider betweenShouldBeTrueProvider
     */
    function testIsBetweenShouldBeTrue($date_target)
    {
        $date_begin = new DateTime('2015-10-01 00:00:00+09:00');
        $date_end = new DateTime('2015-10-01 10:00:00+09:00');

        $period = new MovieViewerPeriod($date_begin, $date_end);

        timecop_freeze($date_target->getTimestamp());

        $this->assertTrue($period->isBetween($date_target));
        $this->assertTrue($period->isBetween());
    }

    function betweenShouldBeTrueProvider()
    {
        return array(
              array(new DateTime('2015-10-01 00:00:00+09:00'))
            , array(new DateTime('2015-10-01 09:00:00+09:00'))
            , array(new DateTime('2015-10-01 10:00:00+09:00'))
        );
    }

    /**
     * @dataProvider betweenShouldBeFalseProvider
     */
    function testIsBetweenShouldBeFalse($date_target)
    {
        $date_begin = new DateTime('2015-10-01 00:00:00+09:00');
        $date_end = new DateTime('2015-10-01 10:00:00+09:00');

        $period = new MovieViewerPeriod($date_begin, $date_end);

        timecop_freeze($date_target->getTimestamp());

        $this->assertFalse($period->isBetween($date_target));
        $this->assertFalse($period->isBetween());
    }

    function betweenShouldBeFalseProvider()
    {
        return array(
              array(new DateTime('2015-09-30 23:59:59+09:00'))
            , array(new DateTime('2015-10-01 10:00:01+09:00'))
        );
    }

    function testIsExpiredShouldBeTrue()
    {
        $date_begin = new DateTime('2015-10-01 00:00:00+09:00');
        $date_end = new DateTime('2015-10-01 10:00:00+09:00');

        $period = new MovieViewerPeriod($date_begin, $date_end);

        $date_target = new DateTime('2015-10-01 10:00:01+09:00');
        timecop_freeze($date_target->getTimestamp());

        $this->assertTrue($period->isExpired($date_target));
        $this->assertTrue($period->isExpired());
    }

    function testIsExpiredShouldBeFalse()
    {
        $date_begin = new DateTime('2015-10-01 00:00:00+09:00');
        $date_end = new DateTime('2015-10-01 10:00:00+09:00');

        $period = new MovieViewerPeriod($date_begin, $date_end);

        $date_target = new DateTime('2015-10-01 09:59:59+09:00');
        timecop_freeze($date_target->getTimestamp());

        $this->assertFalse($period->isExpired($date_target));
        $this->assertFalse($period->isExpired());
    }
}

?>