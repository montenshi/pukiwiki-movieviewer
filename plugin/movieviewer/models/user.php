<?php

/**
 * Pukiwikiプラグイン::動画視聴 設定
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.User
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerCourseRoute
{
    public $course_ids = array();

    function __construct($course_ids)
    {
        $this->course_ids = $course_ids;
    }

    function getFirst()
    {
        return $this->course_ids[0];
    }

    function getNext($course_id)
    {
        $current_index = array_search($course_id, $this->course_ids);

        if ($current_index === false) { // 見つからなかった
            return null;
        }

        if ($current_index + 1 === count($this->course_ids)) {
            return null;
        }

        return $this->course_ids[$current_index + 1];
    }

    function getLast()
    {
        return end($this->course_ids);
    }

}

class MovieViewerCourseRoutes extends ArrayObject
{
    public function __construct()
    { 
        parent::__construct(func_get_args(), ArrayObject::ARRAY_AS_PROPS);
    }

    public function isFirstCourse($course_id)
    {
        foreach ($this as $route) {
            if ($route->getFirst() === $course_id) {
                return true;
            }
        }
        return false;
    }
}

class MovieViewerUser
{
    public $id = '';
    public $firstName = '';
    public $lastName = '';
    public $mailAddress = '';
    public $hashedPassword = '';
    public $memberId = '';
    public $selected_routes = null;

    function __construct()
    {
        $this->selected_routes = new MovieViewerCourseRoutes();
        $this->selected_routes[] = new MovieViewerCourseRoute(array("K1Kiso", "K2Kiso"));
    }

    function setPassword($raw_password)
    {
        $this->hashedPassword = $this->hashPassword($raw_password);
    }

    function hasMemberId()
    {
        return ($this->memberId !== "" && $this->memberId !== null);
    }

    function isAdmin()
    {
        return false;
    }

    function isMainte()
    {
        // 会員番号がAから始まる人はメンテナンスロール
        return plugin_movieviewer_startsWith($this->memberId, "A");
    }
    
    function isTrial()
    {
        // 会員番号が数字のみの人は仮会員
        return mb_ereg_match("^[0-9]", $this->memberId);        
    }

    function verifyPassword($raw_password)
    {
        return strcmp($this->hashedPassword, $this->hashPassword($raw_password)) === 0;
    }

    function describe()
    {
        $value = "";
        if ($this->hasMemberId() && !$this->isTrial()) {
            $value = "{$this->memberId} ";
        }
        $value .= "{$this->lastName} {$this->firstName}";

        return $value;
    }

    function generateResetPasswordToken()
    {
        return new MovieViewerUserResetPasswordToken($this->id);
    }

    function getValidDealPackConfirmations()
    {
        $repo = plugin_movieviewer_get_deal_pack_payment_confirmation_repository();
        $confirmations = $repo->findValidsByUser($this->id);
        
        return $confirmations;
    }
    
    protected function hashPassword($raw_password)
    {
        return hash("sha512", $raw_password);
    }
}

class MovieViewerAdmin extends MovieViewerUser
{
    function isAdmin()
    {
        return true;
    }
}

class MovieViewerCommuUser extends MovieViewerUser
{
    public $commuId;

    function isAdmin()
    {
        return false;
    }

    protected function hashPassword($raw_password)
    {
        return hash("sha1", trim(hash("md5", $raw_password)));
    }
}

class MovieViewerCommuAdmin extends MovieViewerCommuUser
{
    function isAdmin()
    {
        return true;
    }
}

?>