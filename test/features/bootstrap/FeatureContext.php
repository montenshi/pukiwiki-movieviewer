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
        // テストで作成されたデータを削除
        exec("rm -rf /Users/and/development/projects/montenshi/web/resources-test/purchase");
        exec("rm -rf /Users/and/development/projects/montenshi/web/resources-test/users");
        // コピー
        exec("cp -pr ./features/resources/* /Users/and/development/projects/montenshi/web/resources-test");
    }

    /**
     * @When ログアウトする
     */
    public function ログアウトする() {
        $this->visitPath('/index.php?cmd=movieviewer_logout');
    }

    /**
     * @When :page_name ページに移動する
     */
    public function ページに移動する($page_name) {
        $this->visitPath("/index.php?{$page_name}");
    }

    /**
     * @When 動画配信会員専用ページに移動する
     */
     public function 動画配信会員専用ページに移動する() {
         $this->visitPath('/');
         $this->getSession()->getPage()->clickLink("会員専用ページ");
         $this->getSession()->getPage()->clickLink("動画配信会員専用");
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

    /**
     * @Then 入金確認一覧 通知あり に以下の内容が表示されていること:
     */
    public function 入金確認一覧_通知あり_に以下の内容が表示されていること(TableNode $table) {
        $this->入金確認一覧に以下の内容が表示されていること($table, '.purchase-requests-notified');
    }

    /**
     * @Then 入金確認一覧 通知なし に以下の内容が表示されていること:
     */
    public function 入金確認一覧_通知なし_に以下の内容が表示されていること(TableNode $table) {
        $this->入金確認一覧に以下の内容が表示されていること($table, '.purchase-requests-unnotified');
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

    function 入金確認一覧に以下の内容が表示されていること(TableNode $table, $css_requests) {
        $page = $this->getSession()->getPage();

        $detail = $page->find('css', $css_requests);

        $actual = array();
        foreach($detail->find('css', 'tbody')->findAll('css', 'tr') as $row) {
            $columns = $row->findAll('css', 'td');

            $actual_row = array();
            $actual_row["会員番号"] = $columns[1]->getText();
            $actual_row["名前"] = $columns[2]->getText();
            $actual_row["メールアドレス"] = $columns[3]->getText();
            $actual_row["受講対象"] = $columns[4]->getText();

            $actual[] = $actual_row;
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
