<?php

class MovieViewerSettings {
    public $auth_module;
    public $data;
    public $aws;
    public $mail;
    public $contact;
    public $payment;

    public static function loadFromYaml($file) {
        $object = new MovieViewerSettings();
        $data = Spyc::YAMLLoad($file);
        $aws = Spyc::YAMLLoad($data['settings']['aws']['path']);
        $mail = Spyc::YAMLLoad($data['settings']['mail']['path']);
        $object->auth_module = $data['settings']['auth_module'];
        $object->data = $data['settings']['data'];
        $object->aws = $aws;
        $object->mail = new MovieViewerMailSettings($mail['smtp'], $mail['template']);
        $object->contact = $data['settings']['contact'];
        $object->payment = new MovieViewerPaymentSettings($data['settings']['payment']);

        return $object;
    }
}

class MovieViewerMailSettings {
    public $smtp;
    public $template;

    function __construct($smtp, $template) {
        $this->smtp = $smtp;
        $this->template = $template;
    }
}

class MovieViewerPaymentSettings {
    public $bank_transfer;
    public $credit;
    private $extra_methods;
    
    function __construct($data) {
        $this->bank_transfer = $data["bank_transfer"];

        if (isset($data["extra_methods"])) {
            $this->extra_methods = $data["extra_methods"];        
        }

        if (isset($data["credit"])) {
            $this->credit = new MovieViewerPaymentCreditSettings($data["credit"]);
        }
    }
    
    public function isCreditEnabled() {
        return in_array("credit", $this->extra_methods);
    }
}

class MovieViewerPaymentCreditSettings {
    public $acceptable_brands;
    public $paygent;
    
    function __construct($data) {
        $this->acceptable_brands = $data["acceptable_brands"];
        $this->paygent = $data["paygent"];
    }
}

?>