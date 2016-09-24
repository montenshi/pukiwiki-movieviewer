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

class MovieViewerUser
{
    public $id = '';
    public $firstName = '';
    public $lastName = '';
    public $mailAddress = '';
    public $hashedPassword = '';
    public $memberId = '';
    public $selected_courses = array('K1Kiso');
    
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

    function getLastDealPackConfirmation()
    {
        $repo = plugin_movieviewer_get_deal_pack_payment_confirmation_repository();
        $confirmations = $repo->findByCourse($this->id, "*");
        
        if (count($confirmations) === 0) {
            return null;
        }

        return end($confirmations);    
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