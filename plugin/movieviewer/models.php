<?php

require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/vendor/autoload.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . '/spyc.php');
use Aws\CloudFront\CloudFrontClient;

// 設定ファイルから設定を読み込む
function plugin_movieviewer_load_settings() {
    return MovieViewerSettings::loadFromYaml(PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS);
}

// 設定をグローバルに保存する
function plugin_movieviewer_set_global_settings() {
    $settings = plugin_movieviewer_load_settings();

    // カレントのTimezoneを設定に追加
    $settings->timezone = new DateTimeZone("Asia/Tokyo");
    date_default_timezone_set("Asia/Tokyo");

    $cfg = array(
        "movieviewer_settings"     => $settings,
    );

    // $GLOBALSに値が保存される
    set_plugin_messages($cfg);
}

// グローバルから設定を取り出す
function plugin_movieviewer_get_global_settings() {
    // set_plugin_messages で設定されたオブジェクトを返す
    return $GLOBALS['movieviewer_settings'];
}

// 設定にあるタイムゾーンをベースにカレント日時を取り出す
function plugin_movieviewer_now() {
    $settings = plugin_movieviewer_get_global_settings();
    return new DateTime(null, $settings->timezone);
}

// htmlspecialcharsをかける
function plugin_movieviewer_hsc($value) {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

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

class MovieViewerMailSettings {
    public $smtp;
    public $template;

    function __construct($smtp, $template) {
        $this->smtp = $smtp;
        $this->template = $template;
    }
}

class MovieViewerSettings {
    public $auth_module;
    public $data;
    public $aws;
    public $mail;
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
        $object->payment = $data['settings']['payment'];

        return $object;
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

    public function isAdmin() {
        return FALSE;
    }

    public function verifyPassword($raw_password) {
        return strcmp($this->hashedPassword, $this->hashPassword($raw_password)) === 0;
    }

    public function describe() {
        return $this->lastName . " " . $this->firstName;
    }

    public function generateResetPasswordToken() {
        return new MovieViewerUserResetPasswordToken($this->id);
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

    public function getSession($session_id) {
        return $this->sessions[$session_id];
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
        $params = $this->createParams();
        $mail = new Qdmail();
        $mail->smtp(true);
        $mail->smtpServer($params);
        $mail->to($mail_to);
        $mail->from($this->settings->smtp["from"]);
        $mail->errorDisplay(false);

        if (isset($this->settings->smtp["qdmail_debug"])) {
            $mail->debug($this->settings->smtp["qdmail_debug"]);
        }

        return $mail;
    }

    function createParams() {
        $params = array(
              'host'     => $this->settings->smtp["host"]
            , 'port'     => $this->settings->smtp["port"]
            , 'from'     => $this->settings->smtp["from"]
            , 'protocol' => $this->settings->smtp["protocol"]
            , 'user' => $this->settings->smtp["user"]
            , 'pass' => $this->settings->smtp["password"]
        );
        return $params;
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

    public function build($user, $deal_pack_name, $price, $bank_transfer) {

        $settings_local = $this->settings->template["transfer_information"];
        $mail = $this->createMail($user->mailAddress);

        $params = array(
              "user_name" => $user->describe()
            , "deal_pack_name" => $deal_pack_name
            , "bank_account" => $bank_transfer->bank_account
            , "deadline" => $bank_transfer->deadline->format("Y年m月d日")
            , "price" => $price
        );

        $body = $this->renderBody($settings_local["body"], $params);

        $mail->subject($settings_local["subject"]);
        $mail->text($body);
        return $mail;
    }

}

class MovieViewerDealPackNotifyPaymentMailBuilder extends MovieViewerMailBuilder {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function build($user, $deal_pack) {

        $settings_local = $this->settings->template["notify_payment"];
        $mail = $this->createMail($settings_local["to"]);

        $params = array(
              'user_name' => $user->describe()
            , 'deal_pack_name' => $deal_pack->describe()
        );

        $body = $this->renderBody($settings_local["body"], $params);

        $mail->subject($settings_local["subject"]);
        $mail->text($body);
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

        $mail->subject($settings_local["subject"]);
        $mail->text($body);
        return $mail;
    }
}

class MovieViewerAwsCloudFrontUrlBuilder {

    private $cf_settings;

    function __construct($cf_settings) {
        $this->cf_settings = $cf_settings;
    }

    public function buildVideoUrl($course_id, $session_id, $chapter_id, $duration_to_expire = 10) {
        $expires = time() + $duration_to_expire;
        $path = $this->getVideoPath($course_id, $session_id, $chapter_id);

        $policy = $this->createPolicy($expires, $path);

        $signed_params = array(
            "url" => "rtmp://{$this->cf_settings['host']['video']}/{$path}",
            "policy" => $policy
        );

        $client = $this->createClient();
        return $client->getSignedUrl($signed_params);
    }

    public function buildTextUrl($course_id, $session_id) {
        $expires = time() + 10;
        $path = $this->getTextPath($course_id, $session_id);

        $signed_params = array(
            "url" => "https://{$this->cf_settings['host']['text']}/{$path}",
            "expires" => $expires
        );

        $client = $this->createClient();
        return $client->getSignedUrl($signed_params);
    }

    function createClient() {
        $client_config = array(
            'key'         => $this->cf_settings['key'],
            'secret'      => $this->cf_settings['secret'],
            'private_key' => $this->cf_settings['private_key'],
            'key_pair_id' => $this->cf_settings['key_pair_id']
        );
        return CloudFrontClient::factory($client_config);
    }

    function createPolicy($expires, $path) {
        $policy = <<<POLICY
        {
            "Statement": [
                {
                    "Resource": "{$path}",
                    "Condition": {
                        "DateLessThan": {"AWS:EpochTime": {$expires}}
                    }
                }
            ]
        }
POLICY;
        return $policy;
    }

    function getVideoPath($course_id, $session_id, $chapter_id) {
        $course_dir = substr($course_id, 0, 2);
        return "courses/{$course_dir}/{$course_id}{$session_id}_{$chapter_id}.mp4";
    }

    function getTextPath($course_id, $session_id) {
        $course_dir = substr($course_id, 0, 2);
        return "courses/{$course_dir}/{$course_id}{$session_id}.zip";
    }
}
?>