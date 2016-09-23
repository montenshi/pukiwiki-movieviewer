<?php

/**
 * Pukiwikiプラグイン::動画視聴画面
 *
 * PHP version 5.3.4
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewerPlugin
 * @package  Base
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

require_once "movieviewer.ini.php";

/**
 * プラグイン規定関数::初期化処理
 *
 * @return void
 */
function plugin_movieviewer_init()
{
    plugin_movieviewer_set_global_settings();
}

/**
 * プラグイン規定関数::ブロック型で呼び出された場合の処理
 * 認証済みの場合: 視聴画面を生成する
 * 未認証の場合: エラー画面を生成する
 *
 * @return string 視聴画面またはエラー画面(html)
 */
function plugin_movieviewer_convert()
{
    global $vars;

    // 認証済み
    $manager = plugin_movieviewer_get_auth_manager();
    if ($manager->isAuthenticated()) {
            return plugin_movieviewer_convert_show_contents();
    }

    // 認証なし
    return plugin_movieviewer_convert_show_alert();
}

/**
 * プラグイン規定関数::アクション型で呼び出された場合の処理
 * パラメータ ope_type の値により、以下の処理を行う
 * show-movie: 動画再生画面を返す
 * download-text: 指定した単元のテキストをダウンロードするようLocationヘッダを返す
 * download-hls-key: 動画(HLS)再生用のキーを返す
 * 
 * @return array ページ名、内容
 */
function plugin_movieviewer_action()
{

    $user_id = plugin_movieviewer_get_auth_manager()->getUserId();

    $current_user = plugin_movieviewer_get_user_repository()->findById($user_id);

    if ($current_user == null) {
        plugin_movieviewer_action_access_denied();
    }

    $ope_type = plugin_movieviewer_action_get_ope_type();

    if ($ope_type === 'show-movie') {
        return plugin_movieviewer_action_show_movie();
    } else if ($ope_type === 'download-text') {
        return plugin_movieviewer_action_download_text();
    } else if ($ope_type === 'download-hls-key') {
        return plugin_movieviewer_action_download_hls_key();
    }

    return plugin_movieviewer_action_invalid_request();
}

/*-- 以下、内部処理 --*/

/**
 * [ブロック] 未認証時のエラー画面を生成する
 *
 * @return string エラー画面(html)
 */
function plugin_movieviewer_convert_show_alert()
{
    plugin_movieviewer_get_auth_manager()->logout();

    $body =<<<TEXT
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    <p class="caution">動画を見るにはログインが必要です。</p>
TEXT;
    return $body;
}

/**
 * [ブロック] 視聴画面を生成する
 * 視聴可能な単元、受講済みの単元を一覧で表示する
 *
 * @return string 視聴画面(html)
 */
function plugin_movieviewer_convert_show_contents()
{

    global $script;

    $user_id = plugin_movieviewer_get_auth_manager()->getUserId();

    $current_user = plugin_movieviewer_get_user_repository()->findById($user_id);
    $viewing_periods = plugin_movieviewer_get_viewing_periods_by_user_repository()->findById($user_id);

    $body_valid_courses = plugin_movieviewer_convert_render_courses($viewing_periods->getValidPeriods());
    $body_expired_courses = plugin_movieviewer_convert_render_courses($viewing_periods->getExpiredPeriods());

    $hsc = "plugin_movieviewer_hsc";

    $message_env = "";
    if (PLUGIN_MOVIEVIEWER_ENV !== "") {
        $env = PLUGIN_MOVIEVIEWER_ENV;
        $message_env =<<<TEXT
        <div style="position:fixed;top:0px;right:0px;"><p class="caution">環境変更中: $env</p></div>
TEXT;
    }

    $uri_review = plugin_movieviewer_get_script_uri() . "?%3A動画配信会員_再視聴";

    $body = <<<TEXT
        <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
        <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
        <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
        <script src="plugin/movieviewer/assets/js/movieviewer.js"></script>
        <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
        <link href="//vjs.zencdn.net/5.4/video-js.css" rel="stylesheet">
        $message_env
        <div>
            <h2>視聴可能な単元</h2>
        </div>
        <div class="movieviewer-sessions movieviewer-sessions-viewable">
        {$body_valid_courses}
        </div>
        <div>
            <h2>受講済みの単元</h2>
        </div>
        <a href="{$uri_review}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>再視聴の申し込み</a>
        <div class="movieviewer-sessions movieviewer-sessions-attended">
        {$body_expired_courses}
        </div>
        <div id="myModal" title="View Movie">
            <div id="myModal_body"></div>
        </div>
        <script src="https://vjs.zencdn.net/5.4/video.js"></script>
        <script src="$base_uri/plugin/movieviewer/assets/js/videojs-contrib-hls.min.js"></script>    
        <script type="text/javascript">
             window.movieviewer = {};
             window.movieviewer.baseUrl = "{$script}";
        </script>
TEXT;

    return $body;
}

/**
 * [ブロック] 視聴期限から単元の一覧を生成する
 *
 * @param array $viewing_periods 視聴期限
 *
 * @return string 単元の一覧(html)
 */
function plugin_movieviewer_convert_render_courses($viewing_periods) 
{

    if (count($viewing_periods) == 0) {
        return "<div>対象の動画はありません。</div>";
    }

    $body_courses = "";
    $body_course = "";
    $current_course_id = "";

    $hsc = "plugin_movieviewer_hsc";

    $courses = plugin_movieviewer_get_courses_repository()->find();

    $timezone = plugin_movieviewer_get_global_settings()->timezone;
    $date_now = new DateTime(null, $timezone);

    foreach ($viewing_periods as $period) {

        $isValid = $period->isValid($date_now);

        $course = $courses->getCourse($period->course_id);
        $session = $course->getSession($period->session_id);

        $body_chapters = "";
        foreach ($session->chapters as $chapter) {
            $list_item = $hsc($chapter->describe());
            if ($isValid) {
                $list_item = <<<TEXT
                            <a href="#" onclick="return movieviewer_showMovie('{$hsc($course->name)}',
                                '{$hsc($session->describe())}',
                                '{$hsc($chapter->describe())}',
                                '{$hsc($course->id)}',
                                '{$hsc($session->id)}',
                                '{$hsc($chapter->id)}');">{$hsc($chapter->describe())}</a>
TEXT;
            }
            $body_chapters .= <<<TEXT
                        <li>{$list_item}</li>
TEXT;
        }

        if ($isValid) {
            $action = <<<TEXT
                  <button class="movieviewer-course-text-download" id="{$hsc($course->id)}_{$hsc($session->id)}_text_download" style="position:absolute;right:1em;">
                    <span>テキストダウンロード</span>
                  </button>
TEXT;
        }

        $body_session = <<<TEXT
            <div style="position: relative;">
                <h4 class="movieviewer-course-title" id="{$hsc($course->id)}_{$hsc($session->id)}_title">
                  <span>{$hsc($session->describe())}</span>

                  <button class="movieviewer-course-show-chapters" id="{$hsc($course->id)}_{$hsc($session->id)}_show_chapters">
                    <span>チャプター一覧</span>
                  </button>
                  {$action}
                </h4>
                <ul id="{$hsc($course->id)}_{$hsc($session->id)}_list" class="list1" style="display:none;">
                    {$body_chapters}
                </ul>
            </div>
TEXT;

        if ($current_course_id !== $course->id) {

            if ($current_course_id !== "") {
                $body_courses .= $body_course . "</div>";
            }

            $body_course = <<<TEXT
            <div class="movieviewer-course" style="margin-bottom:30px;">
            <h3>{$hsc($course->name)}</h3>
            {$body_session}
TEXT;

            $current_course_id = $course->id;
        } else {
            $body_course .= $body_session;
        }
    }

    if ($body_course !== "") {
        $body_courses .= $body_course . "</div>";
    }

    return $body_courses;
}

/**
 * [アクション] POSTまたはGETで指定されたope_type(処理区分)の値取得する
 *
 * @return string ope_type(処理区分)の値
 */
function plugin_movieviewer_action_get_ope_type()
{
    $ope_type = 'unknown';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $ope_type = filter_input(INPUT_GET, 'ope_type');
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ope_type = filter_input(INPUT_POST, 'ope_type');
    }

    return $ope_type;
}

/**
 * [アクション] エラー処理(リクエスト内容の誤り)
 *
 * @return void
 */
function plugin_movieviewer_action_invalid_request()
{
    plugin_movieviewer_abort("リクエストの内容に誤りがあります。");
}

/**
 * [アクション] エラー処理(認証なし)
 *
 * @return void
 */
function plugin_movieviewer_action_access_denied()
{
    plugin_movieviewer_abort("動画を見るにはログインが必要です。");
}

/**
 * [アクション] 指定した単元のテキストをダウンロードするようLocationヘッダを返す
 *
 * @return void
 */
function plugin_movieviewer_action_download_text()
{
    date_default_timezone_set("Asia/Tokyo");

    $settings = plugin_movieviewer_load_settings();
    $cf_settings = $settings->aws['cloud_front'];

    $target = array(
        "course" => filter_input(INPUT_GET, "course"),
        "session" => filter_input(INPUT_GET, "session"),
    );

    try {
        plugin_movieviewer_validate_course_id($target["course"]);
    } catch (MovieViewerValidationException $ex) {
        plugin_movieviewer_abort("指定した内容に誤りがあります。");
    }

    try {
        plugin_movieviewer_validate_session_id($target["session"]);
    } catch (MovieViewerValidationException $ex) {
        plugin_movieviewer_abort("指定した内容に誤りがあります。");
    }

    $user_id = plugin_movieviewer_get_auth_manager()->getUserId();
    $current_user = plugin_movieviewer_get_user_repository()->findById($user_id);
    $viewing_periods = plugin_movieviewer_get_viewing_periods_by_user_repository()->findById($user_id);

    $canView = $viewing_periods->canView($target['course'], $target['session']);

    if (!$canView) {
        plugin_movieviewer_abort("このテキストはダウンロードできません。");
    }

    $builder = new MovieViewerAwsCloudFrontUrlBuilder($cf_settings);
    $signed_path = $builder->buildTextUrl($target['course'], $target['session']);

    header('Location: ' . $signed_path);
    exit();
}

/**
 * [アクション] 動画再生画面を返す
 *
 * @return void
 */
function plugin_movieviewer_action_show_movie()
{
    date_default_timezone_set("Asia/Tokyo");

    $settings = MovieViewerSettings::loadFromYaml(PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS);
    $cf_settings = $settings->aws['cloud_front'];

    $target = array(
        "course" => filter_input(INPUT_POST, "course"),
        "session" => filter_input(INPUT_POST, "session"),
        "chapter" => filter_input(INPUT_POST, "chapter")
    );

    try {
        plugin_movieviewer_validate_course_id($target["course"]);
    } catch (MovieViewerValidationException $ex) {
        plugin_movieviewer_abort("指定した内容に誤りがあります。");
    }

    try {
        plugin_movieviewer_validate_session_id($target["session"]);
    } catch (MovieViewerValidationException $ex) {
        plugin_movieviewer_abort("指定した内容に誤りがあります。");
    }

    try {
        plugin_movieviewer_validate_chapter_id($target["chapter"]);
    } catch (MovieViewerValidationException $ex) {
        plugin_movieviewer_abort("指定した内容に誤りがあります。");
    }

    $user_id = plugin_movieviewer_get_auth_manager()->getUserId();
    $current_user = plugin_movieviewer_get_user_repository()->findById($user_id);
    $viewing_periods = plugin_movieviewer_get_viewing_periods_by_user_repository()->findById($user_id);

    $canView = $viewing_periods->canView($target['course'], $target['session']);

    if (!$canView) {
        plugin_movieviewer_abort("この動画は見ることができません。");
    }

    $builder = new MovieViewerAwsCloudFrontUrlBuilder($cf_settings);
    $signed_path_rtmp = $builder->buildVideoRTMPUrl($target['course'], $target['session'], $target['chapter'], 24 * 60 * 60);
    $signed_path_hls = $builder->buildVideoHLSUrl($target['course'], $target['session'], $target['chapter'], 24 * 60 * 60);

    $base_uri = plugin_movieviewer_get_base_uri();

    pkwk_common_headers();
    header('Content-type: text/html');
    print <<<EOC
    <video id="my_video_1" class="video-js vjs-default-skin vjs-big-play-centered" preload="auto" controls width="800" height="500"
           data-setup='{"techOrder":["flash","html5"]}'>
        <source src="rtmp://{$cf_settings['host']['video']['rtmp']}/cfx/st/&mp4:{$signed_path_rtmp}" type="rtmp/mp4">
        <source src="{$signed_path_hls}" type="application/x-mpegURL">
    </video>
    <p>
    最大化ボタン <img src="$base_uri/plugin/movieviewer/assets/images/button-maximize.png"> は再生ボタン <img src="$base_uri/plugin/movieviewer/assets/images/button-play.png"> を押した後、表示されます。
    </p>
EOC;
    exit();
}

/**
 * [アクション] 動画(HLS)再生用のキーを返す
 *
 * @return void
 */
function plugin_movieviewer_action_download_hls_key()
{
    pkwk_common_headers();
    header('Content-type: application/octet-stream');

    $settings = MovieViewerSettings::loadFromYaml(PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS);
    $decrypter = new MovieViewerAwsTranscorderEncriptionKeyDecypter(
        $settings->aws['kms'], $settings->aws['transcoder']
    );
    
    print $decrypter->execute();  
    exit();
}

?>