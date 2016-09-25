<?php

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

require_once 'vendor/phpunit/phpunit/src/Framework/Assert/Functions.php';

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawMinkContext implements Context, SnippetAcceptingContext
{
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    /** @BeforeFeature */
    public static function setupFeature(BeforeFeatureScope $scope)
    {
        // テスト中であることをプラグインに知らせる
        exec("touch ../../../htdocs/.movieviewer_env_feature_test");
    }

    /** @AfterFeature */
    public static function teardownFeature(AfterFeatureScope $scope)
    {
        // テストが終わったことをプラグインに知らせる
        exec("rm -f ../../../htdocs/.movieviewer_env_feature_test");
    }

    /** @BeforeScenario */
    public function before(BeforeScenarioScope $scope)
    {
        // テストで作成されたデータを削除
        exec("rm -rf ../../../resources/test/data/purchase");
        exec("rm -rf ../../../resources/test/data/users");
        // コピー
        exec("cp -pr ./features/resources/* ../../../resources/test");
        exec("cp -pr ./features/qhmcommu/commu/data/* ../../../htdocs/commu/data");
        // Syncでコピーが終わるまで待機しているつもり
        sleep(2); // 試しに2秒ほど待つ
    }

    /**
     * @When ログアウトする
     */
    public function ログアウトする()
    {
        $this->visitPath('/index.php?cmd=movieviewer_logout');
    }

    /**
     * @When :page_name ページに移動する
     */
    public function ページに移動する($page_name)
    {
        $this->visitPath("/index.php?{$page_name}");
    }

    /**
     * @When 動画配信会員専用ページに移動する
     */
    public function 動画配信会員専用ページに移動する()
    {
        $this->visitPath('/');
        $this->getSession()->getPage()->clickLink("会員専用ページ");
        $this->getSession()->getPage()->clickLink("動画配信会員専用MyAuth");
    }

     /**
      * @When 受講者 ユーザ :user_name パスワード :user_password でログインする
      */
    public function 受講者_ユーザ_パスワード_でログインする($user_name, $user_password)
    {
        $this->visitPath('/');
        $this->getSession()->getPage()->clickLink("会員専用ページ");
        $this->getSession()->getPage()->clickLink("動画配信会員専用MyAuth");
        $this->getSession()->getPage()->fillField("movieviewer_user", $user_name);
        $this->getSession()->getPage()->fillField("movieviewer_password", $user_password);
        $this->getSession()->getPage()->pressButton("ログインする");
    }

     /**
      * @When 以下の単元を選択する:
      */
    public function 以下の単元を選択する(TableNode $table)
    {
        $page = $this->getSession()->getPage();

        foreach ($table->getHash() as $key => $row) {
            print_r($key);
            print_r($row);
            $course = $page->find('xpath', "//div[h3[contains(./text(), '{$row['コース']}')]]");
            $button = $course->find('xpath', "/fieldset/label[contains(./text(), '{$row['単元']}')]");

            $button->click();
        }
    }

    /**
     * @Then お知らせに以下の内容が表示されていること:
     */
    public function お知らせに以下の内容が表示されていること(PyStringNode $markdown)
    {
        $page = $this->getSession()->getPage();
        $notices = $page->find('css', '.movieviewer-notices');

        assertContains($markdown->getRaw(), $notices->getText());
    }

     /**
      * @Then お知らせに以下の振込情報が表示されていること:
      */
    public function お知らせに以下の振込情報が表示されていること(TableNode $table)
    {
        $page = $this->getSession()->getPage();

        $detail = $page->find('css', '.movieviewer-payment-guide');

        $actual = array();
        
        $rows = $detail->findAll('css', 'tr');

        if (count($rows) === 4) { // クレジットカード支払いが有効でない場合は単純な表なのでヘッダとデータを取り出せば良い
            foreach ($rows as $row) {
                $head = $row->find('css', 'th');
                $data = $row->find('css', 'td');
                $actual[$head->getText()] = $data->getText();
            }
        } else { // クレジットカード支払いが有効な場合は、振込先の情報が複数になるので頑張って解析する
            $actual[$rows[0]->find('css', 'th')->getText()] = $rows[0]->find('css', 'td')->getText();
            $actual[$rows[1]->find('css', 'th')->getText()] = $rows[1]->find('css', 'td')->getText();
            $actual[$rows[4]->find('css', 'th')->getText()] = $rows[4]->find('css', 'td')->getText();

            $targets = $rows[3]->findAll('css', 'td');
            $data = $targets[0]->getText();
            
            // クレジットカードは対応するカード会社を画像で表示しているので
            // ALT属性を取り出して評価することにした
            $images = $targets[1]->findAll('css', 'img');
            foreach ($images as $image) {
                $data .= " " . $image->getAttribute("alt");
            }

            $actual[$rows[2]->find('css', 'th')->getText()] = $data;
        }
        
        assertEquals($table->getRowsHash(), $actual);
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
    public function 受講済みの単元に以下が表示されていること(TableNode $table)
    {
        $this->以下の単元が表示されていること($table, '.movieviewer-sessions-attended');
    }

    /**
     * @Then 視聴可能な単元に何も表示されていないこと
     */
    public function 視聴可能な単元に何も表示されていないこと()
    {
        $this->以下の単元に何も表示されていないこと('.movieviewer-sessions-viewable');
    }

    /**
     * @Then 受講済みの単元に何も表示されていないこと
     */
    public function 受講済みの単元に何も表示されていないこと()
    {
        $this->以下の単元に何も表示されていないこと('.movieviewer-sessions-attended');
    }

    /**
     * @Then 申し込み内容に以下が表示されていること:
     */
    public function 申し込み内容に以下が表示されていること(TableNode $table)
    {
        $page = $this->getSession()->getPage();

        $detail = $page->find('css', '.movieviewer-purchase-request-details');

        $actual = array();
        foreach ($detail->findAll('css', 'tr') as $row) {
            $head = $row->find('css', 'th');
            $data = $row->find('css', 'td');

            $actual[$head->getText()] = $data->getText();
        }

        assertEquals($table->getRowsHash(), $actual);
    }

    /**
     * @Then 入金確認一覧に以下の内容が表示されていること:
     */
    public function 入金確認一覧に以下の内容が表示されていること(TableNode $table)
    {
        $page = $this->getSession()->getPage();

        $css_requests = ".purchase-requests";
        $detail = $page->find('css', $css_requests);

        $actual = array();
        foreach ($detail->find('css', 'tbody')->findAll('css', 'tr') as $row) {
            $columns = $row->findAll('css', 'td');

            $actual_row = array();
            $actual_row["会員番号"] = $columns[1]->getText();
            $actual_row["名前"] = $columns[2]->getText();
            $actual_row["メールアドレス"] = $columns[3]->getText();
            $actual_row["対象"] = $columns[4]->getText();
            $actual_row["申込日"] = $columns[5]->getText();

            $actual[] = $actual_row;
        }

        assertEquals($table->getHash(), $actual);
    }

    /**
     * @Then 再視聴可能な単元に以下が表示されていること:
     */
    public function 再視聴可能な単元に以下が表示されていること(TableNode $table)
    {
        $this->以下の単元ボタンが表示されていること($table);
    }

    function 以下の単元が表示されていること(TableNode $table, $css_sessions)
    {
        $page = $this->getSession()->getPage();

        $div = $page->find('css', $css_sessions);
        $courses = $div->findAll('css', '.movieviewer-course');

        $actual = array();
        foreach ($courses as $course) {
            $course_name = $course->find('css', 'h3')->getText();
            $sessions = $course->findAll('css', 'h4');
            foreach ($sessions as $session) {
                $session_name = $session->find('css', 'span')->getText();
                $actual[] = array('コース'=>$course_name, '単元'=>$session_name);
            }
        }

        assertEquals($table->getHash(), $actual);
    }

    function 以下の単元に何も表示されていないこと($css_sessions)
    {
        $page = $this->getSession()->getPage();

        $div = $page->find('css', $css_sessions);
        $courses = $div->findAll('css', '.movieviewer-course');

        assertCount(0, $courses);
    }

    function 以下の単元ボタンが表示されていること(TableNode $table)
    {
        $page = $this->getSession()->getPage();

        $courses = $page->findAll('css', '.movieviewer-course');

        $actual = array();
        foreach ($courses as $course) {
            $course_name = $course->find('css', 'h3')->getText();
            $sessions = $course->findAll('css', 'label');
            foreach ($sessions as $session) {
                $session_name = $session->getText();
                $actual[] = array('コース'=>$course_name, '単元'=>$session_name);
            }
        }

        assertEquals($table->getHash(), $actual);
    }

    /* qiita.com/kumazo@github/items/e0797004513d9029613e より */
    function rmrf($dir)
    {
        if (is_dir($dir) and !is_link($dir)) {
            array_map(array($this, 'rmrf'),   glob($dir.'/*', GLOB_ONLYDIR));
            array_map('unlink', glob($dir.'/*'));
            rmdir($dir);
        }
    }
}
