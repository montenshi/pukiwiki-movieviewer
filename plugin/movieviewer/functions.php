<?php

// 設定ファイルから設定を読み込む
function plugin_movieviewer_load_settings() {
    $settings = MovieViewerSettings::loadFromYaml(PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS);

    // カレントのTimezoneを設定に追加
    $settings->timezone = new DateTimeZone("Asia/Tokyo");
    date_default_timezone_set("Asia/Tokyo");

    return $settings;
}

// 設定をグローバルに保存する
function plugin_movieviewer_set_global_settings() {
    $settings = plugin_movieviewer_load_settings();

    $cfg = array(
        "movieviewer_settings"     => $settings,
    );

    // $GLOBALSに値が保存される
    set_plugin_messages($cfg);

    // CSRF対策用トークンを生成し、セッションに登録する
    plugin_movieviewer_set_csrf_token();
}

// 設定をグローバルに保存する(forum利用時)
function plugin_movieviewer_set_global_settings_forum() {
    $settings = plugin_movieviewer_load_settings();
    $GLOBALS['movieviewer_settings'] = $settings;
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

// この文字列が、指定された接頭辞で始まるかどうかを判定します。
// http://b.0218.jp/20140514163237.html より。
function plugin_movieviewer_startsWith($haystack, $needle)
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

// この文字列が、指定された接尾辞で終るかどうかを判定します。
// http://b.0218.jp/20140514163237.html より。
function plugin_movieviewer_endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

// <br>を改行に置き換える
// http://hi.seseragiseven.com/archives/559
function plugin_movieviewer_br2nl($value) {
    return preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/i', "\n", $value);
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

function plugin_movieviewer_render_dealpack_offer_price($offer, $text_only = FALSE) {
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

    // 最初の行に空白がないのは、メールの文章に利用するため...
    $price_with_notes =<<<TEXT
{$hsc_num($total_amount_with_tax)}円<br>
    （<span class="{$class_for_unit_amount}">{$hsc_num($unit_amount_without_tax)}</span>円×{$hsc_num($num_units)}ヶ月分＝{$hsc_num($total_amount_without_tax)}円　＋　消費税 {$hsc_num($tax_amount)}円）
    $note_for_unit_amount
TEXT;

    if ($text_only) {
        $price_with_notes = strip_tags($price_with_notes);
    }

    return $price_with_notes;
}

?>