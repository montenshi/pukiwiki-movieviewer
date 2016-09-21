<?php

class MovieViewerUser {
    public $id = '';
    public $firstName = '';
    public $lastName = '';
    public $mailAddress = '';
    public $hashedPassword = '';
    public $memberId = '';
    public $selected_courses = array('K1Kiso');
    
    public function setPassword($raw_password) {
        $this->hashedPassword = $this->hashPassword($raw_password);
    }

    public function hasMemberId() {
        return ($this->memberId !== "" && $this->memberId !== NULL);
    }

    public function isAdmin() {
        return FALSE;
    }

    public function isMainte() {
        // 会員番号がAから始まる人はメンテナンスロール
        return plugin_movieviewer_startsWith($this->memberId, "A");
    }
    
    public function isTrial() {
        // 会員番号が数字のみの人は仮会員
        return mb_ereg_match("^[0-9]", $this->memberId);        
    }

    public function verifyPassword($raw_password) {
        return strcmp($this->hashedPassword, $this->hashPassword($raw_password)) === 0;
    }

    public function describe() {
        $value = "";
        if ($this->hasMemberId() && !$this->isTrial()) {
            $value = "{$this->memberId} ";
        }
        $value .= "{$this->lastName} {$this->firstName}";

        return $value;
    }

    public function generateResetPasswordToken() {
        return new MovieViewerUserResetPasswordToken($this->id);
    }

    public function getLastDealPackConfirmation() {

        $repo = plugin_movieviewer_get_deal_pack_payment_confirmation_repository();
        $confirmations = $repo->findByCourse($this->id, "*");
        
        if (count($confirmations) === 0) {
            return NULL;
        }

        return end($confirmations);    
    }

    public function getValidDealPackConfirmations() {
        $repo = plugin_movieviewer_get_deal_pack_payment_confirmation_repository();
        $confirmations = $repo->findValidsByUser($this->id);
        
        return $confirmations;
    }
    
    protected function hashPassword($raw_password) {
        return hash("sha512", $raw_password);
    }
}

class MovieViewerAdmin extends MovieViewerUser {

    public function isAdmin() {
        return TRUE;
    }
}

class MovieViewerCommuUser extends MovieViewerUser {

    public $commuId;

    public function isAdmin() {
        return FALSE;
    }

    protected function hashPassword($raw_password) {
        return hash("sha1", trim(hash("md5", $raw_password)));
    }
}

class MovieViewerCommuAdmin extends MovieViewerCommuUser {

    public function isAdmin() {
        return TRUE;
    }
}

?>