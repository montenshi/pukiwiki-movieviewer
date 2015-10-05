<?php

class MovieViewerDealPackOfferMakerTest extends PHPUnit_Framework_TestCase {

    public function setUp() {
        $test_dir = getcwd();
        $this->define('PLUGIN_MOVIEVIEWER_COMMU_DIR', dirname(__FILE__) . "/../lib/commu");
        $this->define('PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR', ".");
        $this->define('PLUGIN_MOVIEVIEWER_PLUGIN_DIR', "plugin");
        $this->define('PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR', "plugin/movieviewer");
        $this->define('PLUGIN_MOVIEVIEWER_LOG_DIR', dirname(__FILE__) . "/../logs");
        chdir('../../../../../app/pukiwiki');
        require_once('plugin/movieviewer/models.php');
        require_once('plugin/movieviewer/models_dealpack.php');
        require_once('plugin/movieviewer/models_dealpack_purchase.php');
        require_once('plugin/movieviewer/repositories.php');

        $settings = new MovieViewerSettings();
        $settings->data["dir"] = dirname(__FILE__) . "/../resources";
        $settings->timezone = new DateTimeZone("Asia/Tokyo");
        date_default_timezone_set("Asia/Tokyo");
        $GLOBALS['movieviewer_settings'] = $settings;
    }

    public function testGetOfferReturns2Offers() {
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