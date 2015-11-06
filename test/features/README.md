# 環境構築方法

  1. QHMとQHMCommuをインストールする
  2. 以下のページを作成する (名前は任意)
     + 会員ページ
       - movieviewer_auth, movieviewer_notify_user, movieviewer を呼び出す
       - movieviewer_show_userinfo は任意
     + 申し込み(銀行振込)ページ
       - movieviewer_auth, movieviewer_purchase_start を呼び出す
     + 入金確認ページ
       - movieviewer_purchase_confirm_payment を呼び出す

# テストの実行方法

  1. Commuの管理者のidをadmin パスワードをadminadminにする
  2. Commuのユーザに以下を追加する
     + メールアドレス: aaa@bbb.ccc 姓:動画配信会員 名:1人目
     + メールアドレス: bbb@bbb.ccc 姓:動画配信会員 名:2人目
     + メールアドレス: ccc@bbb.ccc 姓:動画配信会員 名:3人目
  3. movieviewer.ini.phpの PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS を
    テスト用のフォルダに切り替える
  4. resources配下の設定ファイルをテスト用のフォルダにコピーする
  5. mail.ymlの設定を変更する
  6. vendor/bin/behat を実行する