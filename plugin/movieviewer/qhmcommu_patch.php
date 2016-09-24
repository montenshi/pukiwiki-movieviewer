<?php

/**
 * Pukiwikiプラグイン::動画視聴 認証管理
 * QHMCommuの改造に必要な関数群
 * QHMCommu側のファイルから直接 require されることを想定している
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  AuthManagers
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

require_once '../plugin/movieviewer.ini.php';

// フォーラムのアクセスルール
class MovieViewerForumAccessRule
{
    function allowView($user)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isMainte()) {
            return true;
        }

        $repos = plugin_movieviewer_get_viewing_periods_by_user_repository();
        $viewing_periods = $repos->findById($user->id);

        $valid_periods = $viewing_periods->getValidPeriods();

        return (count($valid_periods) > 0);
    }

    function allowPost($user)
    {
        return $user->isAdmin() || $user->isMainte();
    }
}

// 設定をグローバルに保存する
function plugin_movieviewer_qhmcommu_patch_set_global_settings()
{
    $settings = plugin_movieviewer_load_settings();
    $GLOBALS['movieviewer_settings'] = $settings;
}

// フォーラム機能にアクセス制御に必要な情報を付与する
function plugin_movieviewer_qhmcommu_patch_apply_accesscontrol( &$c )
{
    plugin_movieviewer_qhmcommu_patch_set_global_settings();

    try {
        $movieviewer_user = plugin_movieviewer_get_current_user();

        $access_rule = new MovieViewerForumAccessRule();
        $c->set('can_view', $access_rule->allowView($movieviewer_user));
        $c->set('can_post', $access_rule->allowPost($movieviewer_user));

    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        $c->set('can_view', false);
        $c->set('can_post', false);
    }
}

// フォーラムの質問を閲覧する権限があるかどうか (コントローラーでの呼び出しを想定)
function plugin_movieviewer_qhmcommu_patch_allow_view()
{
    plugin_movieviewer_qhmcommu_patch_set_global_settings();

    try {
        $movieviewer_user = plugin_movieviewer_get_current_user();

        $access_rule = new MovieViewerForumAccessRule();

        return $access_rule->allowView($movieviewer_user);
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return false;
    }
}

// フォーラムに質問・回答を投稿する権限があるかどうか (コントローラーでの呼び出しを想定)
function plugin_movieviewer_qhmcommu_patch_allow_post()
{
    plugin_movieviewer_qhmcommu_patch_set_global_settings();

    try {
        $movieviewer_user = plugin_movieviewer_get_current_user();
        $access_rule = new MovieViewerForumAccessRule();

        return $access_rule->allowPost($movieviewer_user);
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return false;
    }
}
?>