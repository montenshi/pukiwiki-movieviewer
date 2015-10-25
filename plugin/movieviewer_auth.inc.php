<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_auth_init(){
  plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_auth_convert(){
    global $vars;

    $manager = plugin_movieviewer_get_auth_manager();

    // 認証開始
    if (isset($_POST['movieviewer_user']) && $_POST['movieviewer_user'] != "") {
       $user_id = $_POST['movieviewer_user'];

       $maybe_user = plugin_movieviewer_get_user_repository()->findById($user_id);

       if ($maybe_user == null){
           plugin_movieviewer_auth_move_to_authpage(TRUE);
       }

       $user_password = $_POST['movieviewer_password'];

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
    $show_messages = isset($_GET['messages']) ? htmlspecialchars($_GET['messages']) : '';

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

    $hsc = "htmlspecialchars";

    $body =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <h2>会員認証</h2>
    <p>以下に、ユーザー名、パスワードを入力し「ログインする」ボタンを押して下さい。</p>
    ${body_messages}
    <form action="index.php?{$page}" METHOD="POST">
        <fieldset style="margin-bottom:10px;">
            <label for="movieviewer_user">ユーザ名</label>
            <input class="text ui-widget-content ui-corner-all" type="text" id="movieviewer_user" name="movieviewer_user" value="{$hsc($_POST['movieviewer_user'])}">
            <label for="movieviewer_password">パスワード</label>
            <input class="ui-widget-content ui-corner-all" type="password" id="movieviewer_password" name="movieviewer_password">
        </fieldset>
        <button class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only" type="submit" style="width: 7em;">ログインする</button>
    </form>
TEXT;
    return $body;
}

?>