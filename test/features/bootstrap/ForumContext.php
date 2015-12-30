<?php

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

require_once('vendor/phpunit/phpunit/src/Framework/Assert/Functions.php');

/**
 * Defines application features from the specific context.
 */
class ForumContext extends RawMinkContext implements Context, SnippetAcceptingContext {
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
     * @Then :alt_text ボタンが表示されていること
     */
     public function ボタンが表示されていること($alt_text) {
         $page = $this->getSession()->getPage();
         $images = $page->findAll('xpath', "//img[@alt='{$alt_text}']");

         assertGreaterThanOrEqual(1, $images);
     }

    /**
     * @Then :alt_text ボタンが表示されていないこと
     */
     public function ボタンが表示されていないこと($alt_text) {
         $page = $this->getSession()->getPage();
         $images = $page->findAll('xpath', "//img[@alt='{$alt_text}']");

         assertCount(0, $images);
     }

    /**
     * @Then コメント欄があること
     */
     public function コメント欄があること() {
         $page = $this->getSession()->getPage();
         $image = $page->find('css', "textarea[name='res/res']");

         assertTrue($image !== NULL);
     }

    /**
     * @Then コメント欄がないこと
     */
     public function コメント欄がないこと() {
         $page = $this->getSession()->getPage();
         $image = $page->find('css', "textarea[name='res/res']");

         assertTrue($image === NULL);
     }
}
