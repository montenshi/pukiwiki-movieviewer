<?php

// カレント(=ログインしている)ユーザを取得する
function plugin_movieviewer_get_current_user() {
    $user_id = plugin_movieviewer_get_auth_manager()->getUserId();
    return plugin_movieviewer_get_user_repository()->findById($user_id);
}

// カレントのページを取得する(ページが分からない場合はデフォルトページを返す)
function plugin_movieviewer_get_current_page() {
    global $vars, $defaultpage;
    return isset($vars['page']) ? $vars['page'] : $defultpage;
}

function plugin_movieviewer_get_auth_manager() {
    return new MovieViewerAuthManagerInCommu();
}

class MovieViewerAuthManager {

    protected $session_varname = "hogehoge";

    function __construct($settings) {
        $this->session_varname = $settings["session_varname"];
    }

    public function login($user_id) {
        $_SESSION[$this->session_varname] = $user_id;
    }

    public function logout() {
        unset($_SESSION[$this->session_varname]);
    }

    public function isAuthenticated() {
        return isset($_SESSION[$this->session_varname]);
    }
}

class MovieViewerDefaultAuthManager extends MovieViewerAuthManager {
    function __construct() {
        parent::__construct(array("session_varname" => "movieviewer_user"));
    }

    public function getUserId() {
        return $_SESSION[$this->session_varname];
    }
}

class MovieViewerAuthManagerInCommu extends MovieViewerAuthManager {
    function __construct() {
        parent::__construct(array("session_varname" => "commu_user"));
    }

    public function getUserId() {
        $id = $_SESSION[$this->session_varname]["email"];

        // 管理者はメールアドレスを持ってないのでnameにする
        if ($id === NULL) {
            $id = $_SESSION[$this->session_varname]["name"];
        }
        return $id;
    }
}

?>