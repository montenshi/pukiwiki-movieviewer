<?php

// QuickCommuから呼び出されているか?
// ディレクトリが commu または forum だった場合に QuickCommu から呼び出されていると判断する
function plugin_movieviewer_require_from_quickcommu() {
    if (substr(getcwd(), - strlen("forum")) === "forum") {
        return TRUE;
    }

    if (substr(getcwd(), - strlen("commu")) === "commu") {
        return TRUE;
    }

    return FALSE;
}

if (!plugin_movieviewer_require_from_quickcommu()) {
    define('PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR', getcwd());
} else {
    define('PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR', getcwd() . "/..");
}

define('PLUGIN_MOVIEVIEWER_COMMU_DIR', PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR . "/commu");
define('PLUGIN_MOVIEVIEWER_PLUGIN_DIR', PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR . "/plugin");
define('PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR', PLUGIN_MOVIEVIEWER_PLUGIN_DIR . "/movieviewer");
define('PLUGIN_MOVIEVIEWER_LOG_DIR', PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR);

# ユーザ設定ファイルを読み込む
require_once(PLUGIN_MOVIEVIEWER_PLUGIN_DIR . '/movieviewer.ini.user.php');

if (!file_exists(PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR . "/.movieviewer_env_feature_test")) {
    define('PLUGIN_MOVIEVIEWER_ENV', '');
    define('PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS', PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS_USER_DEFAULT);
} else {
    define('PLUGIN_MOVIEVIEWER_ENV', 'Feature Test');
    define('PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS', PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS_USER_TEST);
    // 強制的に時間を固定する (要:timecopのインストール)
    $date_freeze = new DateTime("2015-11-14 23:59:59+09:00", new DateTimeZone("Asia/Tokyo"));
    timecop_freeze($date_freeze->getTimestamp());
}

define('PLUGIN_MOVIEVIEWER_AUTH_MODULE_DEFAULT', "default");
define('PLUGIN_MOVIEVIEWER_AUTH_MODULE_COMMU', "commu");

require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/functions.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models_dealpack.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models_dealpack_purchase.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models_payment.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/repositories.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/validators.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/managers.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/vendor/autoload.php");
require_once(PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR . "/lib/qdmail.php");
require_once(PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR . "/lib/qdsmtp.php");

?>