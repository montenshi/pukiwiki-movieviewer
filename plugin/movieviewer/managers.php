<?php

// PukiwikiのベースURIを取得する
function plugin_movieviewer_get_base_uri() {
    return dirname(plugin_movieviewer_get_script_uri());
}

function plugin_movieviewer_get_script_uri() {
    $url  = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $url .= $_SERVER['HTTP_HOST'];
    if ($_SERVER['SERVER_PORT'] !== 80) {
        $url .= ':' . $_SERVER['SERVER_PORT'];
    }
    $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
    $url .= $uri_parts[0];
    return $url;
}

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
    $settings = plugin_movieviewer_get_global_settings();
    return MovieViewerAuthManagerFactory::createInstance($settings->auth_module);
}

class MovieViewerAuthManagerFactory {
    public static function createInstance($auth_module) {
        if ($auth_module === PLUGIN_MOVIEVIEWER_AUTH_MODULE_COMMU) {
            return new MovieViewerAuthManagerInCommu();
        } else {
            return new MovieViewerDefaultAuthManager();
        }
    }
}

class MovieViewerAuthManager {

    protected $session_varname = "hogehoge";

    function __construct($settings) {
        $this->session_varname = $settings["session_varname"];
    }

    public function getLabelForUserName() {
        return "ユーザ名";
    }

    public function login($user) {
        $_SESSION[$this->session_varname] = $user->id;
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

    public function getLabelForUserName() {
        return "メールアドレス";
    }

    public function login($user) {
        // コミュ管理者をmovieviewer_authでログインできるようにするための仮実装
        $_SESSION[$this->session_varname] = array("name" => $user->id);
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