<?php

require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/vendor/autoload.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . '/spyc.php');

// ログの出力 plugins/movieviewer に出力される(10日分)
class MovieViewerLogger {
    static $logger = null;

    public static function getLogger() {
        if (self::$logger === null) {
            $log_path = PLUGIN_MOVIEVIEWER_LOG_DIR . "/movieviewer.log";
            self::$logger = new \Monolog\Logger('movieviewer');
            self::$logger->pushHandler(new \Monolog\Handler\RotatingFileHandler($log_path, 10, \Monolog\Logger::INFO));
            $ip = new \Monolog\Processor\IntrospectionProcessor(
                \Monolog\Logger::DEBUG,
                array(
                'Monolog\\',
                'Illuminate\\',
                )
            );
            self::$logger->pushProcessor($ip);
        }
        return self::$logger;
    }
}

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
        
        return $valid_confirmations;
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

class MovieViewerUserResetPasswordToken {
    public $id;
    public $user_id;
    public $date_exipire;

    function __construct($user_id) {
        $this->id = hash("md5", mt_rand());
        $this->user_id = $user_id;
        $this->date_expire = plugin_movieviewer_now()->add(new DateInterval('PT1H'));
    }

    public function isValid($date_target = null) {
        // 指定のない場合は現在日時
        if ($date_target == null) {
            $timezone = plugin_movieviewer_get_global_settings()->timezone;
            $date_target = new DateTime(null, $timezone);
        }

        if ($this->date_expire >= $date_target) {
            return TRUE;
        }
        return FALSE;
    }
}

class MovieViewerCourse {
    public $id = '';
    public $name = '';
    public $sessions = array();

    public function getId() {
        return $this->id;
    }

    public function getIdShort() {
        return substr($this->id, 0, 2);
    }

    public function getSession($session_id) {
        return $this->sessions[$session_id];
    }

    public function describe() {
        return $this->name;
    }

    public function describeShort() {
        return $this->name;
    }

}

class MovieViewerCourses {

    private $courses = array();

    public function addCourse($course) {
        $this->courses[$course->id] = $course;
    }

    public function getCourse($course_id) {
        return $this->courses[$course_id];
    }
}

class MovieViewerSession {
    public $id = '';
    public $name = '';
    public $chapters = array();

    public function getChapter($chapter_id) {
        return $this->sessions[$chapter_id];
    }

    public function describe() {
        return $this->name;
    }

    public function describeShort() {
        return mb_substr($this->name, 1, mb_strrpos($this->name, "回")-1);
    }
}

class MovieViewerChapter {
    public $id = '';
    public $name = '';
    public $time = 0;

    public function describe() {
        return $this->id . ". " . $this->name . " (" . $this->time . "分)";
    }
}

class MovieViewerPeriod {
    public $date_begin;
    public $date_end;

    function __construct($date_begin, $date_end) {
        $this->date_begin = $date_begin;
        $this->date_end = $date_end;
    }

    public function isBefore($date_target = null) {
        if ($date_target === null) {
            $date_target = plugin_movieviewer_now();
        }

        return ($this->date_begin > $date_target);
    }

    public function isBetween($date_target = null) {
        if ($date_target === null) {
            $date_target = plugin_movieviewer_now();
        }

        return ($this->date_begin <= $date_target && $date_target <= $this->date_end);
    }

    public function isExpired($date_target = null) {
        if ($date_target === null) {
            $date_target = plugin_movieviewer_now();
        }

        return ($this->date_end < $date_target);
    }

    public function aboutToExpire($date_target = null) {
        if ($date_target === null) {
            $date_target = plugin_movieviewer_now();
        }

        if ($this->isExpired($date_target)) {
            return FALSE;
        }

        $date_calc = new DateTime($date_target->format('Y-m-d H:i:sP'));
        
        return ($date_calc->add(new DateInterval('P1M')) >= $this->date_end);
    }
}

class MovieViewerViewingPeriod {
    public $course_id;
    public $session_id;
    public $date_begin;
    public $date_end;

    function __construct($course_id, $session_id, $date_begin, $date_end) {
        $this->course_id = $course_id;
        $this->session_id = $session_id;
        $this->date_begin = $date_begin;
        $this->date_end = $date_end;
    }

    public function isExpired($target) {
        $timezone = plugin_movieviewer_get_global_settings()->timezone;
        $target_dateonly = new DateTime($target->format('Y-m-d'), $timezone);

        if ($this->date_end < $target_dateonly) {
            return TRUE;
        }
        return FALSE;
    }

    public function isValid($target) {
        $timezone = plugin_movieviewer_get_global_settings()->timezone;
        $target_dateonly = new DateTime($target->format('Y-m-d'), $timezone);

        if (($this->date_end >= $target_dateonly) && ($this->date_begin <= $target_dateonly)) {
            return TRUE;
        }
        return FALSE;
    }

    public function getDurationToEnd($target) {
        $timezone = plugin_movieviewer_get_global_settings()->timezone;
        $target_dateonly = new DateTime($target->format('Y-m-d'), $timezone);

        return $this->date_end->diff($target_dateonly);
    }
}

class MovieViewerViewingPeriodsByUser {
    public $user_id;
    private $periods = array();

    function __construct($user_id) {
        $this->user_id = $user_id;
    }

    public function getValidPeriods($date_target = null) {
        // 指定のない場合は現在日時
        if ($date_target == null) {
            $timezone = plugin_movieviewer_get_global_settings()->timezone;
            $date_target = new DateTime(null, $timezone);
        }

        $objects = array();

        foreach ($this->periods as $period) {
            if ($period->isValid($date_target)) {
                $objects[] = $period;
            }
        }

        return $objects;
    }

    public function getExpiredPeriods($date_target = null) {
        // 指定のない場合は現在日時
        if ($date_target == null) {
            $timezone = plugin_movieviewer_get_global_settings()->timezone;
            $date_target = new DateTime(null, $timezone);
        }

        $objects = array();

        foreach ($this->periods as $period) {
            if ($period->isExpired($date_target)) {
                $objects[] = $period;
            }
        }

        return $objects;
    }

    public function getAllPeriods() {
        $objects = array();

        foreach ($this->periods as $period) {
            $objects[] = $period;
        }

        return $objects;
    }

    public function addPeriod($course_id, $session_id, $date_begin, $date_end) {
        $period = new MovieViewerViewingPeriod($course_id, $session_id, $date_begin, $date_end);
        $this->periods[$this->getKey($course_id, $session_id)] = $period;
    }

    public function canView($course_id, $session_id, $date_target = null) {
        // 指定のない場合は現在日時
        if ($date_target == null) {
            $timezone = plugin_movieviewer_get_global_settings()->timezone;
            $date_target = new DateTime(null, $timezone);
        }

        $period = $this->periods[$this->getKey($course_id, $session_id)];

        if ($period == null) {
            return FALSE;
        }

        return $period->isValid($date_target);
    }

    private function getKey($course_id, $session_id) {
        return $course_id . ":" . $session_id;
    }
}

class MovieViewerMailBuilder {
    protected $settings;

    function __construct($settings) {
        $this->settings = $settings;
    }

    function createMail($mail_to) {
        $mail = new PHPMailer();
        $mail->IsHTML(false);
        
        $mail->IsSMTP();
        $mail->Host = $this->settings->smtp["host"];
        $mail->SMTPAuth = $this->settings->smtp["smtp_auth"];
        if (isset($this->settings->smtp["encryption_protocol"])) {
            $mail->SMTPSecure = $this->settings->smtp["encryption_protocol"];
        }
        $mail->Port = $this->settings->smtp["port"];

        $mail->Username = $this->settings->smtp["user"];
        $mail->Password = $this->settings->smtp["password"]; 
        $mail->CharSet = $this->settings->smtp["charset"];

        $mail->SetFrom($this->settings->smtp["from"]);
        $mail->From = $this->settings->smtp["from"];
        $mail->AddAddress($mail_to);

        $mail->SMTPDebug = 0;
        if (isset($this->settings->smtp["debug"])) {
            $mail->SMTPDebug = 1;
        }

        return $mail;
    }

    function renderBody($template, $params) {
        $regex = '/{{\s*([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*}}/s';
        return preg_replace_callback($regex, function ($m) use ($params) {
            if (!isset($params[$m[1]])) {
                return '';
            }

            return $params[$m[1]];
        }, $template);

    }
}

class MovieViewerDealPackBankTransferInformationMailBuilder extends MovieViewerMailBuilder {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function build($user, $deal_pack_name, $price, $bank_transfer, $deadline) {

        $settings_local = $this->settings->template["transfer_information"];
        $mail = $this->createMail($user->mailAddress);

        $params = array(
              "user_name" => $user->describe()
            , "deal_pack_name" => $deal_pack_name
            , "bank_accounts_with_notes" => "{$bank_transfer->bank_accounts_with_notes}"
            , "deadline" => $deadline->format("Y年m月d日")
            , "price" => $price
        );

        $body = $this->renderBody($settings_local["body"], $params);

        $mail->Subject = $settings_local["subject"];
        $mail->Body = $body;
        return $mail;
    }

}

class MovieViewerResetPasswordMailBuilder extends MovieViewerMailBuilder {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function build($mail_to, $reset_url) {

        $settings_local = $this->settings->template["reset_password"];
        $mail = $this->createMail($mail_to);

        $body = $this->renderBody($settings_local["body"], array('reset_url' => $reset_url));

        $mail->Subject = $settings_local["subject"];
        $mail->Body = $body;
        return $mail;
    }
}
?>