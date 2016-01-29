# 環境構築方法

  1. QHMとQHMCommuをインストールする
  2. php_timecopをインストールする
     + https://github.com/hnw/php-timecop を参照
     + php.iniを変更した場合は、apacheを再起動すること
  3. 以下のページを作成する (名前は任意)
     + マイページ
       - movieviewer_auth, movieviewer_notify_user, movieviewer を呼び出す
       - movieviewer_show_userinfo は任意
     + 申し込み(銀行振込)ページ
       - movieviewer_auth, movieviewer_purchase_start を呼び出す
     + 入金確認ページ
       - movieviewer_purchase_confirm_payment を呼び出す

# テストの実行方法

  1. Commuの管理者のidをadmin パスワードをadminadminにする
  2. Commuのユーザに以下を追加する パスワードは全て hogehoge にする
     + メールアドレス: aaa@bbb.ccc 姓:動画配信会員 名:1人目 カスタム項目1:N1-151101 (正規会員 K1基礎セットの2つ目を受講中、3つ目が継続割引)
     + メールアドレス: bbb@bbb.ccc 姓:動画配信会員 名:2人目 カスタム項目1:N0-151101 (正規会員 K1基礎セットの1つ目が終了、2つ目が定価)
     + メールアドレス: ccc@bbb.ccc 姓:動画配信会員 名:3人目 カスタム項目1:00-000001 (仮会員 K1基礎セットの1つ目を申し込み中)
     + メールアドレス: ddd@bbb.ccc 姓:動画配信会員 名:4人目 カスタム項目1:          (仮会員 申し込みまだ、会員番号振り忘れ)
  4. resources/mail.yml.sample をコピーし、from, user, password を修正、mail.yml を作成する
  3. vendor/bin/behat を実行する