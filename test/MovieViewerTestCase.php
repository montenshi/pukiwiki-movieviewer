<?php

class MovieViewerTestCase extends PHPUnit_Framework_TestCase {

    public function setUp() {
        // プラグインの動作に必要な設定 (movieviewer.ini.phpで行うコトを代理する)
        $test_dir = getcwd();
        $this->define('PLUGIN_MOVIEVIEWER_COMMU_DIR', dirname(__FILE__) . "/lib/commu");
        $this->define('PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR', ".");
        $this->define('PLUGIN_MOVIEVIEWER_PLUGIN_DIR', "plugin");
        $this->define('PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR', "plugin/movieviewer");
        $this->define('PLUGIN_MOVIEVIEWER_LOG_DIR', dirname(__FILE__) . "/logs");

        chdir('../../../../../app/pukiwiki');
        require_once('plugin/movieviewer/models.php');
        require_once('plugin/movieviewer/models_dealpack.php');
        require_once('plugin/movieviewer/models_dealpack_purchase.php');
        require_once('plugin/movieviewer/repositories.php');
    }

    protected function setGlobalSettings($settings) {
        // グローバルな設定
        date_default_timezone_set($settings->timezone->getName());
        $GLOBALS['movieviewer_settings'] = $settings;
    }

    protected function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}

?>