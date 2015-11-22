<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_reset_password_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_reset_password_convert(){
    return plugin_movieviewer_reset_password_generate_request_page();
}

function plugin_movieviewer_reset_password_generate_request_page($messages){
    $manager = plugin_movieviewer_get_auth_manager();
    $manager->logout();

    global $vars, $defaultpage;

    $page = isset($vars['page']) ? $vars['page'] : $defultpage;
    $show_messages = isset($_GET['messages']) ? plugin_movieviewer_hsc(filter_input(INPUT_GET, 'messages')) : '';

    $messages = "";
    if ($show_messages) {
        $messages = "入力したユーザは登録されていません。";
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

    $body =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <h2>パスワードの再設定</h2>
    <p>以下に、ユーザー名を入力し「メールを送る」ボタンを押して下さい。<br>
    登録しているメールアドレスにパスワード再設定手続きのお知らせメールが届きます。</p>
    ${body_messages}
    <form action="index.php?cmd=movieviewer_reset_password" METHOD="POST">
        <input type="hidden" name="ope_type" value="request">
        <input type="hidden" name="page" value="$page">
        {$input_csrf_token()}
        <fieldset style="margin-bottom:10px;">
            <label for="movieviewer_user">ユーザ名</label>
            <input class="text ui-widget-content ui-corner-all" type="text" id="movieviewer_user" name="movieviewer_user">
        </fieldset>
        <button class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only" type="submit" style="width: 7em;">メールを送る</button>
    </form>
TEXT;
    return $body;
}

function plugin_movieviewer_reset_password_action(){
    plugin_movieviewer_set_global_settings();

    $ope_type = plugin_movieviewer_reset_password_action_get_ope_type();

    if ($ope_type === 'request') {
        return plugin_movieviewer_reset_password_action_request();
    } else if ($ope_type === 'confirm') {
        return plugin_movieviewer_reset_password_action_confirm();
    } else if ($ope_type === 'reset') {
        return plugin_movieviewer_reset_password_action_reset();
    } else if ($ope_type === 'delete_expired_tokens') {
        return plugin_movieviewer_reset_password_delete_expired_tokens();
    }

    return plugin_movieviewer_reset_password_action_invalid_request();
}

function plugin_movieviewer_reset_password_action_get_ope_type(){
    $ope_type = 'unknown';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $ope_type = filter_input(INPUT_GET, 'ope_type');
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ope_type = filter_input(INPUT_POST, 'ope_type');
    }

    return $ope_type;
}

function plugin_movieviewer_reset_password_action_request(){

    $page = plugin_movieviewer_get_current_page();

    try {
        plugin_movieviewer_validate_csrf_token();
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_reset_password_error("不正なリクエストです。");
    }

    $user_id = filter_iput(INPUT_POST, 'movieviewer_user');

    try {
        $user = plugin_movieviewer_get_user_repository()->findById($user_id);
    } catch ( MovieViewerRepositoryObjectNotFoundException $ex ) {
        return plugin_movieviewer_reset_password_error("ユーザ ${user_id} は登録されていません。");
    }

    if ($user->mailAddress === null || $user->mailAddress === '') {
        return plugin_movieviewer_reset_password_error("ユーザ ${user_id} のメールアドレスが登録されていません。");
    }

    $token = $user->generateResetPasswordToken();

    try {
        plugin_movieviewer_get_user_reset_password_token_repository()->store($token);
    } catch ( MovieViewerRepositoryObjectCantStoreException $ex ) {
        return plugin_movieviewer_reset_password_error("処理に失敗しました。");
    }

    $settings = plugin_movieviewer_get_global_settings();
    $builder = new MovieViewerResetPasswordMailBuilder($settings->mail);

    $reset_uri = plugin_movieviewer_get_script_uri() . "?cmd=movieviewer_reset_password&page=${page}&ope_type=confirm&token=" . $token->id;
    $mail = $builder->build($user->mailAddress, $reset_uri);
    $result = $mail->send();

    $hsc = "plugin_movieviewer_hsc";

    $message = <<<TEXT
    ユーザ {$hsc($user_id)} のメールアドレスにパスワード再設定手続きのお知らせを送りました。<br>
    <br>
    有効期限は1時間です。ご注意ください。<br>
    有効期限を過ぎた場合は、あらためてパスワード再設定のお手続きをお願いします。<br>
TEXT;
    return array("msg"=>$page, "body"=>$message);
}

function plugin_movieviewer_reset_password_action_confirm(){

    $page = plugin_movieviewer_get_current_page();

    $token_id = filter_input(INPUT_GET, 'token');

    try {
        $token = plugin_movieviewer_get_user_reset_password_token_repository()->findById($token_id);
    } catch (MovieViewerRepositoryObjectNotFoundException $ex ) {
        MovieViewerLogger::getLogger()->addWarning(
            "トークンが見つからない", array("token" => $token_id));

        return plugin_movieviewer_reset_password_error("無効なリンクです。");
    }

    if (!$token->isValid()) {
        return plugin_movieviewer_reset_password_error("有効期限が切れています。");
    }

    try {
        $user = plugin_movieviewer_get_user_repository()->findById($token->user_id);
    } catch (MovieViewerRepositoryObjectNotFoundException $ex ) {
        MovieViewerLogger::getLogger()->addError(
            "ユーザが見つからない", array("token" => $token_id, "user" => $token->user_id));

        return plugin_movieviewer_reset_password_error("無効なリンクです。");
    }

    $content = plugin_movieviewer_reset_password_action_confirm_generate_page($token, $user);

    return array("msg"=>$page, "body"=>$content);
}

function plugin_movieviewer_reset_password_action_confirm_generate_page($token, $user, $messages = ""){
    $manager = plugin_movieviewer_get_auth_manager();
    $manager->logout();

    $page = plugin_movieviewer_get_current_page();

    $body_messages = "";
    if ($messages !== null && $messages !== "") {
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

    $body =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <h2>パスワードのリセット</h2>
    <div><span style="font-size:1.2em;font-weight:bold;">{$hsc($user->describe())}様</span></div>
    <p>以下に、パスワードを入力し「パスワードをリセットする」ボタンを押して下さい。</p>
    ${body_messages}
    <form action="index.php?cmd=movieviewer_reset_password" METHOD="POST">
        <input type="hidden" name="ope_type" value="reset">
        <input type="hidden" name="page" value="$hsc($page)">
        <input type="hidden" name="token" value="$hsc($token->id)">
        {$input_csrf_token()}
        <fieldset style="margin-bottom:10px;">
            <label for="movieviewer_user">パスワード</label>
            <input class="text ui-widget-content ui-corner-all" type="password" id="movieviewer_password" name="movieviewer_password">
            <label for="movieviewer_user">パスワード(確認)</label>
            <input class="text ui-widget-content ui-corner-all" type="password" id="movieviewer_password_confirm" name="movieviewer_password_confirm">
        </fieldset>
        <button class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only" type="submit" style="width: 7em;">パスワードをリセットする</button>
    </form>
TEXT;
    return $body;
}

function plugin_movieviewer_reset_password_action_reset(){

    $page = plugin_movieviewer_get_current_page();

    try {
        plugin_movieviewer_validate_csrf_token();
    } catch (MovieViewerValidationException $ex) {
        return plugin_movieviewer_reset_password_error("不正なリクエストです。");
    }

    $token_id = filter_input(INPUT_POST, 'token');

    try {
        $token = plugin_movieviewer_get_user_reset_password_token_repository()->findById($token_id);
    } catch (MovieViewerRepositoryObjectNotFoundException $ex ) {
        MovieViewerLogger::getLogger()->addError(
            "トークンが見つからない", array("token" => $token_id));

        return plugin_movieviewer_reset_password_error("パスワードの再設定ができませんでした。");
    }

    try {
        $user = plugin_movieviewer_get_user_repository()->findById($token->user_id);
    } catch (MovieViewerRepositoryObjectNotFoundException $ex ) {
        MovieViewerLogger::getLogger()->addError(
            "ユーザが見つからない", array("token" => $token_id, "user" => $token->user_id));

        return plugin_movieviewer_reset_password_error("パスワードの再設定ができませんでした。");
    }

    $password = filter_input(INPUT_POST, 'movieviewer_password');
    $password_confirm = filter_input(INPUT_POST, 'movieviewer_password_confirm');

    if ($password !== $password_confirm) {
        $content = plugin_movieviewer_reset_password_action_confirm_generate_page($token, $user, "入力したパスワードとパスワード(確認)が一致しません。");
        return array("msg"=>$page, "body"=>$content);
    }

    $user->setPassword($password);

    plugin_movieviewer_get_user_repository()->store($user);

    plugin_movieviewer_get_user_reset_password_token_repository()->delete($token);

    $content =<<<TEXT
    パスワードを更新しました。
TEXT;

    return array("msg"=>$page, "body"=>$content);
}

function plugin_movieviewer_reset_password_delete_expired_tokens(){
    plugin_movieviewer_get_user_reset_password_token_repository()->deleteExpiredTokens();
}

function plugin_movieviewer_reset_password_action_invalid_request(){
    return array("msg"=>"エラー", "body"=>"<p>リクエストの内容に誤りがあります。</p>");
}

function plugin_movieviewer_reset_password_error($custom_message){
    $page = plugin_movieviewer_get_current_page();
    $message = plugin_movieviewer_reset_password_generate_error_message($page, $custom_message);
    return array("msg" => $page, "body" => $message);
}

function plugin_movieviewer_reset_password_generate_error_message($page, $custom_message){
    $reset_uri = plugin_movieviewer_reset_password_get_script_uri($page);
    $message =<<<TEXT
    $custom_message<br>
    <br>
    <a href='${reset_uri}'>パスワード再設定ページ</a>からもう一度やり直してください。<br>
TEXT;
    return $message;
}

function plugin_movieviewer_reset_password_get_script_uri($page){
    $base_uri = plugin_movieviewer_get_script_uri();
    return "$base_uri?$page";
}

?>