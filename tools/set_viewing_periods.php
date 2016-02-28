<?php

define("DATA_BASE_DIR", "./data");
define("DATE_BEGIN", "2016-02-20 00:00:00+09:00");
define("DATE_END", "2016-02-29 23:59:59+09:00");

// defineした値をヒアドキュメントで使うためのおまじない
function with($v){
  return $v;
}
$with = "with";

// コマンドの引数
$email = $argv[1];
$course = $argv[2];

// 今日の日付
$date_now = new DateTime("now", new DateTimeZone('Asia/Tokyo'));
$date_now_str = $date_now->format('Y-m-d H:i:sP');

// 申込のデータを作る
$base_dir = DATA_BASE_DIR . "/purchase/deal_pack/${course}";
$file = "${base_dir}/${email}_purchase_request.yml";
$content =<<<TEXT
---
user_id: ${email}
pack_id: ${course}
date_requested: ${date_now_str}
TEXT;
file_put_contents($file, $content);

// 入金確認のデータを作る
$base_dir = DATA_BASE_DIR . "/purchase/deal_pack/${course}/confirmed";
$file = "${base_dir}/${email}_purchase_confirm_payment.yml";
$content =<<<TEXT
---
user_id: ${email}
pack_id: ${course}
date_confirmed: ${date_now_str}
viewing_period:
  date_begin: {$with(DATE_BEGIN)}
  date_end: {$with(DATE_END)}
TEXT;
file_put_contents($file, $content);

// 視聴期限のデータを作る
$user_data_dir = DATA_BASE_DIR . "/users/${email}";

// ディレクトリがなかったら作る
if (!file_exists($user_data_dir)) {
  if (!mkdir($user_data_dir, 0766, true)) {
    die("ディレクトリが作れませんでした $user_data_dir");
  }
}

$file = "${user_data_dir}/viewing_periods.yml";
$content =<<<TEXT
---
viewing_periods:
  -
    course_id: K1Kiso
    session_id: 05
    date_begin: {$with(DATE_BEGIN)}
    date_end: {$with(DATE_END)} 
  -
    course_id: K1Kiso
    session_id: 06
    date_begin: {$with(DATE_BEGIN)}
    date_end: {$with(DATE_END)} 
  -
    course_id: K1Kiso
    session_id: 07
    date_begin: {$with(DATE_BEGIN)}
    date_end: {$with(DATE_END)} 
  -
    course_id: K1Kiso
    session_id: 08
    date_begin: {$with(DATE_BEGIN)}
    date_end: {$with(DATE_END)} 
TEXT;
file_put_contents($file, $content);


?>
