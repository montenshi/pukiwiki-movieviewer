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

    /**
     * @Then 視聴可能な単元に以下が表示されていること:
     */
    public function 視聴可能な単元に以下が表示されていること(TableNode $table)
    {
        $this->以下の単元が表示されていること($table, '.movieviewer-sessions-viewable');
    }

    /**
     * @Then 受講済みの単元に以下が表示されていること:
     */
    public function 受講済みの単元に以下が表示されていること(TableNode $table) {
        $this->以下の単元が表示されていること($table, '.movieviewer-sessions-attended');
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
}
