<?php

// PukiwikiのベースURIを取得する
function plugin_movieviewer_get_base_uri() {
    return dirname(plugin_movieviewer_get_script_uri());
}

function plugin_movieviewer_get_script_uri() {
    $url  = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $url .= $_SERVER['HTTP_HOST'];
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
        $this->setUserInfoToSession($user, $this->session_varname);
        if ($user->isAdmin()) {
            $this->setUserInfoToSession($user, "commu_admin");
        }
        
        $this->updateLastLogin($user);
    }

    public function logout() {
        unset($_SESSION[$this->session_varname]);
        unset($_SESSION["commu_admin"]);
        unset($_SESSION["forum_user"]);
    }


    public function getUserId() {
        $id = $_SESSION[$this->session_varname]["email"];

        // 管理者はメールアドレスを持ってないのでnameにする
        if ($id === NULL) {
            $id = $_SESSION[$this->session_varname]["name"];
        }

        return $id;
    }

    private function setUserInfoToSession($user, $session_varname) {
        $_SESSION[$session_varname] = array(
              "name" => $user->id // Commu管理者をログインできるようにするための項目
            , "id" => $user->commuId
            , "lastname" => $user->lastName
            , "firstname" => $user->firstName
            , "email" => $user->id
            , "created" => NULL
            , "state" => NULL
            , "zip" => NULL
            , "phone" => NULL
            , "job" => NULL
            , "custom1" => $user->memberId
        );
    }
    
    private function updateLastLogin($user) {
        $repo = plugin_movieviewer_get_user_repository();
        $repo->updateLastLogin($user);
    }
}

?>