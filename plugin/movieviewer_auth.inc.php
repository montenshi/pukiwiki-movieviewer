<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_auth_init(){
  plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_auth_convert(){
    global $vars;

    $manager = plugin_movieviewer_get_auth_manager();

    $req_user_id = filter_input(INPUT_POST, "movieviewer_user");

    // 認証開始
    if ($req_user_id !== NULL && $req_user_id !== "") {

        try {
            plugin_movieviewer_validate_csrf_token();
        } catch (MovieViewerValidationException $ex) {
            return plugin_movieviewer_auth_move_to_authpage(TRUE);
        }

        try {
            plugin_movieviewer_validate_user_id($req_user_id);
        } catch (MovieViewerValidationException $ex) {
            return plugin_movieviewer_auth_move_to_authpage(TRUE);
        }

        try {
            $maybe_user = plugin_movieviewer_get_user_repository()->findById($req_user_id);
        } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
            plugin_movieviewer_auth_move_to_authpage(TRUE);
        }

        $user_password = filter_input(INPUT_POST, 'movieviewer_password');

        if (!$maybe_user->verifyPassword($user_password)) {
            return plugin_movieviewer_auth_move_to_authpage(TRUE);
        }

        $manager->login($maybe_user);

       return '';
    }

    // 認証済み
    if ($manager->isAuthenticated()) {
       return '';
    }

    // 認証なし
    return plugin_movieviewer_auth_move_to_authpage(FALSE);
}

function plugin_movieviewer_auth_move_to_authpage($messages) {

    global $vars, $script;

    $page = isset($vars['page']) ? $vars['page'] : '';

    // 戻り先のページを指定する
    $auth_url = $script
                . "?plugin=movieviewer_auth"
                . "&messages=" . urlencode($messages)
                . "&page=" . urlencode($page);

    header("Location: $auth_url");
    exit();
}

function plugin_movieviewer_auth_action(){

    global $vars;

    $page = isset($vars['page']) ? $vars['page'] : $defultpage;
    $title = "${page}（認証）";

    $body = plugin_movieviewer_auth_generate_signin_page();
    return array('msg'=>$title, 'body'=>$body);
}

function plugin_movieviewer_auth_generate_signin_page($messages){
    $manager = plugin_movieviewer_get_auth_manager();
    $manager->logout();

    global $vars, $defaultpage;

    $page = isset($vars['page']) ? $vars['page'] : $defultpage;
    $show_messages = isset($_GET['messages']) ? plugin_movieviewer_hsc(filter_input(INPUT_GET, 'messages')) : '';

    $messages = "";
    if ($show_messages) {
        $messages = "ユーザ名またはパスワードに誤りがあります。";
    }

    $body_messages = "";
    if ($messages != null && $messages != "") {
        $body_messages =<<<TEXT
        <div class="ui-state-error ui-corner-all" style="margin: 5px;">
        <p>
            <span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
            ${messages}
        </p>
        </div>
TEXT;
    }

    $hsc = "plugin_movieviewer_hsc";
    $input_csrf_token = "plugin_movieviewer_generate_input_csrf_token";

    $auth_manager = plugin_movieviewer_get_auth_manager();
    $label_for_username = $hsc($auth_manager->getLabelForUserName());

    $body =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/movieviewer.css" rel="stylesheet">
    <h2>会員認証</h2>
    <p>以下に、{$label_for_username}、パスワードを入力し「ログインする」ボタンを押して下さい。</p>
    ${body_messages}
    <form class="movieviewer" action="index.php?{$page}" METHOD="POST">
        <fieldset>
            <label for="movieviewer_user">{$label_for_username}</label>
            <input type="text" id="movieviewer_user" name="movieviewer_user" size=50>
        </fieldset>
        <fieldset>
            <label for="movieviewer_password">パスワード</label>
            <input type="password" id="movieviewer_password" name="movieviewer_password" size=50>
        </fieldset>
        {$input_csrf_token()}
        <button class="movieviewer-button" type="submit">ログインする</button>
    </form>
TEXT;
    return $body;
}

?>