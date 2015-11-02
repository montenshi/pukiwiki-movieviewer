<?php

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

require_once('vendor/phpunit/phpunit/src/Framework/Assert/Functions.php');

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawMinkContext implements Context, SnippetAcceptingContext {
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct() {
    }

    /** @BeforeScenario */
    public function before(BeforeScenarioScope $scope) {
        $this->rmrf("/Users/and/development/projects/montenshi/web/resources-test/purchase/deal_pack/K1Kiso-3");
    }

    /**
     * @Then お知らせに以下の内容が表示されていること:
     */
     public function お知らせに以下の内容が表示されていること(PyStringNode $markdown) {
         $page = $this->getSession()->getPage();
         $notices = $page->find('css', '.movieviewer-notices');

         assertContains($markdown->getRaw(), $notices->getText());
     }

    /**
     * @Then 視聴可能な単元に以下が表示されていること:
     */
    public function 視聴可能な単元に以下が表示されていること(TableNode $table) {
        $this->以下の単元が表示されていること($table, '.movieviewer-sessions-viewable');
    }

    /**
     * @Then 受講済みの単元に以下が表示されていること:
     */
    public function 受講済みの単元に以下が表示されていること(TableNode $table) {
        $this->以下の単元が表示されていること($table, '.movieviewer-sessions-attended');
    }

    /**
     * @Then 申し込み内容に以下が表示されていること:
     */
    public function 申し込み内容に以下が表示されていること(TableNode $table) {
        $page = $this->getSession()->getPage();

        $detail = $page->find('css', '.movieviewer-purchase-request-details');

        $actual = array();
        foreach($detail->findAll('css', 'tr') as $row) {
            $head = $row->find('css', 'th');
            $data = $row->find('css', 'td');

            $actual[$head->getText()] = $data->getText();
        }

        assertEquals($table->getRowsHash(), $actual);
    }

    function 以下の単元が表示されていること(TableNode $table, $css_sessions) {
        $page = $this->getSession()->getPage();

        $div = $page->find('css', $css_sessions);
        $courses = $div->findAll('css', '.movieviewer-course');

        $actual = array();
        foreach($courses as $course) {
            $course_name = $course->find('css', 'h3')->getText();
            $sessions = $course->findAll('css', 'h4');
            foreach($sessions as $session) {
                $session_name = $session->find('css', 'span')->getText();
                $actual[] = array('コース'=>$course_name, '単元'=>$session_name);
            }
        }

        assertEquals($table->getHash(), $actual);
    }

    /* qiita.com/kumazo@github/items/e0797004513d9029613e より */
    function rmrf($dir) {
        if (is_dir($dir) and !is_link($dir)) {
            array_map(array($this, 'rmrf'),   glob($dir.'/*', GLOB_ONLYDIR));
            array_map('unlink', glob($dir.'/*'));
            rmdir($dir);
        }
    }
}
