# language: ja
フィーチャ: 簡単な導通テスト

  シナリオ: ログインから入金完了まで
    前提    ホームページを表示している
    もし    "会員専用ページ" のリンク先へ移動する
    ならば  "動画配信会員専用MyAuth" と表示されていること

    # ログインとお知らせの確認
    もし    "動画配信会員専用MyAuth" のリンク先へ移動する
      かつ  "movieviewer_user" フィールドに "aaa@bbb.ccc" と入力する
      かつ  "movieviewer_password" フィールドに "hogehoge" と入力する
      かつ  "ログインする" ボタンをクリックする
    ならば  "動画配信会員 1人目さん" と表示されていること
      かつ  視聴可能な単元に以下が表示されていること:
        | コース     | 単元   |
        | 基礎コース１年目 | 第５回 |
        | 基礎コース１年目 | 第６回 |
        | 基礎コース１年目 | 第７回 |
        | 基礎コース１年目 | 第８回 |
      かつ  受講済みの単元に以下が表示されていること:
        | コース     | 単元   |
        | 基礎コース１年目 | 第１回 |
        | 基礎コース１年目 | 第２回 |
        | 基礎コース１年目 | 第３回 |
        | 基礎コース１年目 | 第４回 |
      かつ  お知らせに以下の内容が表示されていること:
        """
        基礎コース１年目 第９回～第１２回の受講ができるようになりました。
        """

    # 受講申し込み
    もし    "銀行振り込みで申し込み" のリンク先へ移動する
    ならば  "ご登録のアドレスにも同じ内容をメールでお送りしています。" と表示されていること
      かつ  申し込み内容に以下が表示されていること:
        | 項目 | 基礎コース１年目 第９回～第１２回 |
        | 金額 | 19,440円                     |
        | 振込先 | ほげふが銀行 なんとか支店 (普) 12345678 フガフガ銀行 なんとか支店 (普) 12345679 ホゲホゲ銀行 なんとか支店 (普) 12345680 |
        | 振込期限 | 2015年11月30日まで         |

    # 入金の完了の通知
    もし    動画配信会員専用ページに移動する
    ならば  "以下の受講セットを申し込んでいますが、入金の通知をいただいておりません。" と表示されていること
      かつ  お知らせに以下の内容が表示されていること:
        """
        基礎コース１年目 第９回～第１２回 入金の完了を通知する
        """
    もし    "入金の完了を通知する" のリンク先へ移動する
    ならば  "入金完了をエンジェルズハウス研究所(AHL)に通知しました。" と表示されていること
      かつ  "受講セット: 基礎コース１年目 第９回～第１２回" と表示されていること

    # 入金完了通知後の表示確認
    もし    動画配信会員専用ページに移動する
    ならば  "以下の受講セットを申し込んでいます。" と表示されていること
      かつ  お知らせに以下の内容が表示されていること:
        """
        基礎コース１年目 第９回～第１２回 入金を確認中です。受講開始までお待ち下さい。
        """

    # 入金確認
    もし    ログアウトする
      かつ  "動画配信会員入金確認" ページに移動する
      かつ  "movieviewer_user" フィールドに "admin" と入力する
      かつ  "movieviewer_password" フィールドに "adminadmin" と入力する
      かつ  "ログインする" ボタンをクリックする
    ならば  "入金が確認できたものにチェックを付けて、確認ボタンを押してください。" と表示されていること
      かつ  入金確認一覧 通知あり に以下の内容が表示されていること:
        | 会員番号 | 名前             | メールアドレス | 受講対象                      |
        |         | 動画配信会員 1人目 | aaa@bbb.ccc  | 基礎コース１年目 第９回～第１２回 |
      かつ  入金確認一覧 通知なし に以下の内容が表示されていること:
        | 会員番号 | 名前             | メールアドレス | 受講対象                     |
        |         | 動画配信会員 3人目 | ccc@bbb.ccc  | 基礎コース１年目 第１回～第４回 |
    もし    "pr_aaa@bbb.ccc###K1Kiso-3" にチェックをつける
      かつ  "確認" ボタンをクリックする
    ならば  "以下の申し込みの入金を確定します。確認の上、確定ボタンを押してください。" と表示されていること
    もし    "確定" ボタンをクリックする

    # 入金確認後の表示確認
    ならば  "以下の申し込みの入金を確定しました。" と表示されていること
    もし    "動画配信会員入金確認" ページに移動する
    ならば  "通知あり" と表示されていないこと
      かつ  入金確認一覧 通知なし に以下の内容が表示されていること:
      | 会員番号 | 名前             | メールアドレス | 受講対象                     |
      |         | 動画配信会員 3人目 | ccc@bbb.ccc  | 基礎コース１年目 第１回～第４回 |
