<?php

#--- 以下は変更しない

define('PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR', getcwd());
define('PLUGIN_MOVIEVIEWER_COMMU_DIR', PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR . "/commu");
define('PLUGIN_MOVIEVIEWER_PLUGIN_DIR', PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR . "/plugin");
define('PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR', PLUGIN_MOVIEVIEWER_PLUGIN_DIR . "/movieviewer");
define('PLUGIN_MOVIEVIEWER_LOG_DIR', PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR);

# ユーザ設定ファイルを読み込む
require_once(PLUGIN_MOVIEVIEWER_PLUGIN_DIR . '/movieviewer.ini.user.php');

if (!file_exists(".movieviewer_env_feature_test")) {
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

require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models_dealpack.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models_dealpack_purchase.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/repositories.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/validators.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/managers.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/vendor/autoload.php");
require_once(PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR . "/lib/qdmail.php");
require_once(PLUGIN_MOVIEVIEWER_PUKIWIKI_DIR . "/lib/qdsmtp.php");

// 設定ファイルから設定を読み込む
function plugin_movieviewer_load_settings() {
    return MovieViewerSettings::loadFromYaml(PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS);
}

// 設定をグローバルに保存する
function plugin_movieviewer_set_global_settings() {
    $settings = plugin_movieviewer_load_settings();

    // カレントのTimezoneを設定に追加
    $settings->timezone = new DateTimeZone("Asia/Tokyo");
    date_default_timezone_set("Asia/Tokyo");

    $cfg = array(
        "movieviewer_settings"     => $settings,
    );

    // $GLOBALSに値が保存される
    set_plugin_messages($cfg);

    // CSRF対策用トークンを生成し、セッションに登録する
    plugin_movieviewer_set_csrf_token();
}

// グローバルから設定を取り出す
function plugin_movieviewer_get_global_settings() {
    // set_plugin_messages で設定されたオブジェクトを返す
    return $GLOBALS['movieviewer_settings'];
}

// 設定にあるタイムゾーンをベースにカレント日時を取り出す
function plugin_movieviewer_now() {
    $settings = plugin_movieviewer_get_global_settings();
    return new DateTime(null, $settings->timezone);
}

// htmlspecialcharsをかける
function plugin_movieviewer_hsc($value) {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

// 数字のフォーマットをかけて、さらにhtmlspecialcharsをかける(必要ない気はするが安全策)
function plugin_movieviewer_hsc_number_format($value) {
    return plugin_movieviewer_hsc(number_format($value));
}

// Convertで呼び出されたページのエラーレスポンスを生成する
function plugin_movieviewer_convert_error_response($message) {
    $hsc = "plugin_movieviewer_hsc";

    $content =<<<TEXT
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <p class="caution">{$hsc($message)}</p>
TEXT;

    return $content;
}

// Actionで呼び出されたページのエラーレスポンスを生成する
function plugin_movieviewer_action_error_response($page, $message) {
    $content = plugin_movieviewer_convert_error_response($message);
    return array("msg"=>$page, "body"=>$content);
}

// アボート(=勝手にメッセージ送信して終了する)
function plugin_movieviewer_abort($message) {
    $hsc = "plugin_movieviewer_hsc";

    header('Content-type: text/html; charset=UTF-8');
    print <<<EOC
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <p class="caution">{$hsc($message)}</p>
EOC;
    exit();
}

// csrf対策用tokenを生成する
// http://qiita.com/yoh-nak/items/c264d29eb25f4df7f19e より
function plugin_movieviewer_set_csrf_token() {
    // すでに登録されている場合は何もしない
    if (isset($_SESSION['csrf_token'])) {
        return;
    }
    $_SESSION['csrf_token'] = rtrim(base64_encode(openssl_random_pseudo_bytes(32)),'=');
}

function plugin_movieviewer_generate_input_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        plugin_movieviewer_set_csrf_token();
    }

    $element =<<<TEXT
    <input type="hidden" name="csrf_token" value="{$_SESSION['csrf_token']}">
TEXT;

    return $element;
}

function plugin_movieviewer_render_dealpack_offer_price($offer) {
    $price = $offer->getPrice();

    $total_amount_with_tax = $price->getTotalAmountWithTax();
    $total_amount_without_tax = $price->getTotalAmountWithoutTax();
    $unit_amount_without_tax = $price->unit_amount_without_tax;
    $num_units = $price->num_units;
    $tax_amount = $price->tax_amount;

    $class_for_unit_amount = "";
    $note_for_unit_amount = "";
    if ($offer->canDiscount()) {
        $class_for_unit_amount = "caution";

        $discount_type = "継続";
        if ($offer->isFirstPurchase()) {
            $discount_type = "新規";
        }

        $note_for_unit_amount = "<br><span class=\"caution\">※ {$discount_type}特別割引</span>";
    }

    $hsc = "plugin_movieviewer_hsc";
    $hsc_num = "plugin_movieviewer_hsc_number_format";

    $price_with_notes =<<<TEXT
    {$hsc_num($total_amount_with_tax)}円<br>
    （<span class="{$class_for_unit_amount}">{$hsc_num($unit_amount_without_tax)}</span>円×{$hsc_num($num_units)}ヶ月分＝{$hsc_num($total_amount_without_tax)}円　＋　消費税 {$hsc_num($tax_amount)}円）
    $note_for_unit_amount
TEXT;

    return $price_with_notes;
}

?>