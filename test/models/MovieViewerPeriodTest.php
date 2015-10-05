<?php

class MovieViewerPeriodTest extends PHPUnit_Framework_TestCase {

    public function setUp() {
        if (!defined('PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR')) {
            define('PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR', "plugin/movieviewer");
        }
        chdir('../../../../../app/pukiwiki');
        require_once('plugin/movieviewer/models.php');
    }

    public function testIsExpiredShouldBeTrue() {
        $date_begin = new DateTime('2015-10-01 00:00:00+09:00');
        $date_end = new DateTime('2015-10-01 10:00:00+09:00');

        $period = new MovieViewerPeriod($date_begin, $date_end);

        $this->assertTrue($period->isExpired(new DateTime('2015-10-01 10:00:01+09:00')));
    }

    public function testIsExpiredShouldBeFalse() {
        $date_begin = new DateTime('2015-10-01 00:00:00+09:00');
        $date_end = new DateTime('2015-10-01 10:00:00+09:00');

        $period = new MovieViewerPeriod($date_begin, $date_end);

        $this->assertFalse($period->isExpired(new DateTime('2015-10-01 09:59:59+09:00')));
    }
}

?>