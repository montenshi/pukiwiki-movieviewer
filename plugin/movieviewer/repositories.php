<?php

require_once(PLUGIN_MOVIEVIEWER_PLUGIN_DIR . "/movieviewer/spyc.php");
require_once(PLUGIN_MOVIEVIEWER_COMMU_DIR . '/cheetan/db/textsql.php');

function plugin_movieviewer_get_user_repository() {
    $settings = plugin_movieviewer_get_global_settings();
    return MovieViewerUserRepositoryFactory::createInstance($settings->auth_module, $settings);
}

function plugin_movieviewer_get_courses_repository() {
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerCoursesRepositoryInFile($settings);
}

function plugin_movieviewer_get_viewing_periods_by_user_repository() {
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerViewingPeriodsByUserRepositoryInFile($settings);
}

function plugin_movieviewer_get_user_reset_password_token_repository() {
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerUserResetPasswordTokenRepositoryInFile($settings);
}

function plugin_movieviewer_get_deal_pack_repository() {
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerDealPackRepositoryInFile($settings);
}

function plugin_movieviewer_get_deal_pack_purchase_request_repository() {
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerDealPackPurchaseRequestRepositoryInFile($settings);
}

function plugin_movieviewer_get_deal_pack_payment_confirmation_repository() {
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerDealPackPaymentConfirmationRepositoryInFile($settings);
}

class MovieViewerRepositoryObjectNotFoundException extends Exception {}

class MovieViewerRepositoryObjectCantStoreException extends Exception {}

class MovieViewerRepositoryInFile {

    const DEFAULT_DATETIME_FORMAT = "Y-m-d H:i:sP";

    protected $settings = array();

    function __construct($settings) {
        $this->settings = $settings;
    }

    protected function storeToYaml($file_path, $data) {

        $dir_path = dirname($file_path);

        if (!file_exists($dir_path)) {
            mkdir($dir_path, 0777, TRUE);
        }

        $fp = fopen($file_path, 'w');

        if ($fp === FALSE) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path));

            throw new MovieViewerRepositoryObjectCantStoreException("ファイルオープンに失敗");
        }

        if (!flock($fp, LOCK_EX)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのロックに失敗", array("file" => $file_path));

            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのロックに失敗");
        }

        if (fputs($fp, Spyc::YAMLDump($data)) === FALSE) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルの書きこみに失敗", array("file" => $file_path));

            flock($fp, LOCK_UN);
            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルの書きこみに失敗");
        }

        if (fflush($fp) === FALSE) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのフラッシュに失敗", array("file" => $file_path));

            flock($fp, LOCK_UN);
            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのフラッシュに失敗");
        }

        if (!flock($fp, LOCK_UN)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのロック解除に失敗", array("file" => $file_path));

            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのロック解除に失敗");
        }

        if (!fclose($fp)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのクローズに失敗", array("file" => $file_path));

            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのクローズに失敗");
        }
    }

    function convertToDateTime($yaml_value) {
        $date_target = new DateTime(null, $this->settings->timezone);
        $date_target->setTimestamp(strtotime($yaml_value));
        return $date_target;
    }
}

class MovieViewerUserRepositoryFactory {
    public static function createInstance($auth_module, $settings) {
        if ($auth_module === PLUGIN_MOVIEVIEWER_AUTH_MODULE_COMMU) {
            return new MovieViewerUserRepositoryInCommuDb($settings);
        } else {
            return new MovieViewerUserRepositoryInFile($settings);
        }
    }
}

class MovieViewerUserRepositoryInCommuDb extends MovieViewerRepositoryInFile {
    function __construct($settings) {
        parent::__construct($settings);
    }

    public function findById($id) {

        if ($this->isAdmin($id)) {
            return $this->createAdmin();
        }

        $db = new CTextDB(PLUGIN_MOVIEVIEWER_COMMU_DIR . "/data/user.txt");
        $result = $db->select('$email=='."'".$id."'");

        if (count($result) !== 1) {
            MovieViewerLogger::getLogger()->addError(
                "ユーザが見つからない", array("id" => $id));

            throw new MovieViewerRepositoryObjectNotFoundException();
        }

        $data = $result[0];

        $object = new MovieViewerCommuUser();
        $object->id = $data["email"];
        $object->firstName = $data["firstname"];
        $object->lastName = $data["lastname"];
        $object->mailAddress = $data["email"];
        $object->hashedPassword = $data["password"];
        $object->memberId = $data["custom1"];
        $object->commuId = $data["id"];

        return $object;
    }

    public function store($object) {
        return Exception("Not Implement");
    }

    function isAdmin($id) {
        $db = new CTextDB(PLUGIN_MOVIEVIEWER_COMMU_DIR . "/data/admin.txt");
        $result = $db->select('$id==\'1\'');

        if (count($result) !== 1) {
            return FALSE;
        }

        $adminId = $result[0]['value'];

        return ($adminId === $id);
    }

    function createAdmin() {
        $db = new CTextDB(PLUGIN_MOVIEVIEWER_COMMU_DIR . "/data/admin.txt");
        $result = $db->select('$id==\'1\'');
        $id = $result[0]['value'];

        $result = $db->select('$id==\'2\'');
        $hashedPassword = $result[0]['value'];

        $object = new MovieViewerCommuAdmin();
        $object->id = $id;
        $object->firstName = "Admin";
        $object->lastName = "Commu";
        $object->hashedPassword = $hashedPassword;

        return $object;
    }
}

class MovieViewerUserRepositoryInFile extends MovieViewerRepositoryInFile {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function findById($id) {

        $file_path = $this->getFilePath($id);

        if (!file_exists($file_path)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path));

            throw new MovieViewerRepositoryObjectNotFoundException();
        }

        $yaml = Spyc::YAMLLoad($file_path);
        $object = new MovieViewerUser();
        $object->id = $yaml["id"];
        $object->firstName = $yaml["firstName"];
        $object->lastName = $yaml["lastName"];
        $object->mailAddress = $yaml["mailAddress"];
        $object->hashedPassword = $yaml["hashedPassword"];
        $object->memberId = $yaml["memberId"];

        return $object;
    }

    public function store($object) {

        $file_path = $this->getFilePath($object->id);

        $fp = fopen($file_path, 'w');

        if ($fp === FALSE) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path));

            throw new MovieViewerRepositoryObjectCantStoreException("ファイルオープンに失敗");
        }

        if (!flock($fp, LOCK_EX)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのロックに失敗", array("file" => $file_path));

            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのロックに失敗");
        }

        $data = array();
        $data["id"] = $object->id;
        $data["firstName"] = $object->firstName;
        $data["lastName"] = $object->lastName;
        $data["mailAddress"] = $object->mailAddress;
        $data["hashedPassword"] = $object->hashedPassword;
        $data["memberId"] = $object->memberId;

        if (fputs($fp, Spyc::YAMLDump($data)) === FALSE) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルの書きこみに失敗", array("file" => $file_path));

            flock($fp, LOCK_UN);
            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルの書きこみに失敗");
        }

        if (fflush($fp) === FALSE) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのフラッシュに失敗", array("file" => $file_path));

            flock($fp, LOCK_UN);
            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのフラッシュに失敗");
        }

        if (!flock($fp, LOCK_UN)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのロック解除に失敗", array("file" => $file_path));

            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのロック解除に失敗");
        }

        if (!fclose($fp)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのクローズに失敗", array("file" => $file_path));

            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのクローズに失敗");
        }
    }

    function getFilePath($id) {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/users/${id}/user.yml";
    }
}

class MovieViewerCoursesRepositoryInFile extends MovieViewerRepositoryInFile {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function find() {
        $object = new MovieViewerCourses();
        $yaml = Spyc::YAMLLoad($this->getFilePath());
        foreach ($yaml["courses"] as $data) {
            $course = new MovieViewerCourse();

            $course->id = $data["id"];
            $course->name = $data["name"];

            foreach ($data["sessions"] as $data_session) {
                $session = new MovieViewerSession();
                $session->id = $data_session["id"];
                $session->name = $data_session["name"];

                foreach ($data_session["chapters"] as $data_chapter) {
                    $chapter = new MovieViewerChapter();
                    $chapter->id = $data_chapter["id"];
                    $chapter->name = $data_chapter["name"];
                    $chapter->time = $data_chapter["time"];
                    $session->chapters[$chapter->id] = $chapter;
                }

                $course->sessions[$session->id] = $session;
            }

            $object->addCourse($course);
        }

        return $object;
    }

    function getFilePath() {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/courses.yml";
    }
}

class MovieViewerViewingPeriodsByUserRepositoryInFile extends MovieViewerRepositoryInFile {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function findById($id) {

        $yaml = Spyc::YAMLLoad($this->getFilePath($id));
        $object = new MovieViewerViewingPeriodsByUser($id);

        $timezone = plugin_movieviewer_get_global_settings()->timezone;
        foreach ($yaml['viewing_periods'] as $period) {

            $date_begin = new DateTime($period["date_begin"], $timezone);
            $date_end   = new DateTime($period["date_end"], $timezone);

            $period["session_id"] = sprintf("%02d", $period["session_id"]);

            $object->addPeriod($period["course_id"], $period["session_id"],
                               $date_begin, $date_end);
        }

        return $object;
    }

    public function store($object) {

        $data = array();
        $data["viewing_periods"] = array();

        foreach($object->getAllPeriods() as $period) {
            $data_period = array();
            $data_period["course_id"] = $period->course_id;
            $data_period["session_id"] = $period->session_id;
            $data_period["date_begin"] = $period->date_begin->format(self::DEFAULT_DATETIME_FORMAT);
            $data_period["date_end"] = $period->date_end->format(self::DEFAULT_DATETIME_FORMAT);

            $data["viewing_periods"][] = $data_period;
        }

        $file_path = $this->getFilePath($object->user_id);
        $this->storeToYaml($file_path, $data);
    }

    function getFilePath($id) {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/users/${id}/viewing_periods.yml";
    }
}

class MovieViewerUserResetPasswordTokenRepositoryInFile extends MovieViewerRepositoryInFile {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function findById($id) {
        $data_dir = $this->getDirPath();
        $files = glob("${data_dir}/${id}_*.yml");

        if (count($files) === 0) {
            MovieViewerLogger::getLogger()->addError(
                "トークンが見つからない", array("data_dir" => $data_dir, "token_id" => $id));

            throw new MovieViewerRepositoryObjectNotFoundException();
        }

        $yaml = Spyc::YAMLLoad($files[0]);
        $token = new MovieViewerUserResetPasswordToken();
        $token->id = $id;
        $token->user_id = $yaml["user_id"];
        $date_expire = $this->convertToDateTime($yaml["date_expire"]);
        $token->date_expire = $date_expire;

        return $token;
    }

    public function store($object) {

        $this->cleanUpToken($object->user_id);

        $file_path = $this->getFilePath($object->id, $object->user_id);

        $fp = fopen($file_path, 'w');

        if ($fp === FALSE) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path));

            throw new MovieViewerRepositoryObjectCantStoreException();
        }

        if (!flock($fp, LOCK_EX)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのロックに失敗", array("file" => $file_path));

            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのロックに失敗");
        }

        $data = array();
        $data["id"] = $object->id;
        $data["user_id"] = $object->user_id;
        $data["date_expire"] = $object->date_expire->format(self::DEFAULT_DATETIME_FORMAT);

        if (fputs($fp, Spyc::YAMLDump($data)) === FALSE) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルの書きこみに失敗", array("file" => $file_path));

            flock($fp, LOCK_UN);
            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルの書きこみに失敗");
        }

        if (fflush($fp) === FALSE) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのフラッシュに失敗", array("file" => $file_path));

            flock($fp, LOCK_UN);
            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのフラッシュに失敗");
        }

        if (!flock($fp, LOCK_UN)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのロック解除に失敗", array("file" => $file_path));

            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのロック解除に失敗");
        }

        if (!fclose($fp)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのクローズに失敗", array("file" => $file_path));

            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのクローズに失敗");
        }
    }

    public function delete($object) {
        $file_path = $this->getFilePath($object->id, $object->user_id);
        unlink($file_path);
    }

    public function deleteExpiredTokens($date_target = null) {
        // 指定のない場合は現在日時
        if ($date_target == null) {
            $timezone = $this->settings->timezone;
            $date_target = new DateTime(null, $timezone);
        }

        $data_dir = $this->getDirPath();
        foreach( glob("${data_dir}/*.yml") as $file ) {
            $yaml = Spyc::YAMLLoad($file);
            $date_expire = $this->convertToDateTime($yaml["date_expire"]);

            if ($date_expire < $date_target) {
                MovieViewerLogger::getLogger()->addInfo(
                    "期限切れのトークンを削除", array("file" => $file, "date_expire" => $yaml["date_expire"]));

                unlink($file);
            }
        }
    }

    function cleanUpToken($user_id) {
        $data_dir = $this->getDirPath();
        foreach( glob("${data_dir}/*_${user_id}.yml") as $file ) {
            unlink($file);
        }
    }

    function getFilePath($token_id, $user_id) {
        $data_dir = $this->getDirPath();
        return "${data_dir}/${token_id}_${user_id}.yml";
    }

    function getDirPath() {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/reset_password";
    }
}

class MovieViewerDealPackRepositoryInFile extends MovieViewerRepositoryInFile {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function findById($pack_id) {
        //TODO: DealPackを永続化する方法を検討する
        $container = new MovieViewerS4DealContainer();
        $pack = $container->getPack($pack_id);

        if ($pack === NULL) {
            MovieViewerLogger::getLogger()->addError(
                "パックが見つからない", array("pack_id" => $pack_id));

            throw new MovieViewerRepositoryObjectCantStoreException();
        }

        return $pack;
    }
}

class MovieViewerDealPackPurchaseRequestRepositoryInFile extends MovieViewerRepositoryInFile {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function findById($request_id) {
        list($user_id, $pack_id) = mb_split("###", $request_id, 2);

        return $this->findBy($user_id, $pack_id);
    }

    public function findBy($user_id, $pack_id) {

        $file_path = $this->getFilePath($user_id, $pack_id);

        if (!file_exists($file_path)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path));

            throw new MovieViewerRepositoryObjectNotFoundException();
        }

        $object = $this->createObject($file_path);

        return $object;
    }

    public function findRequestingByUser($user_id) {

        $objects = array();

        $repo_conf = plugin_movieviewer_get_deal_pack_payment_confirmation_repository();

        $data_dir = $this->getGlobPathByUser($user_id);
        foreach( glob($data_dir) as $file_path ) {
            $object = $this->createObject($file_path);

            if ($repo_conf->exists($object->user_id, $object->pack_id)) {
                continue;
            }

            $objects[] = $object;
        }

        return $objects;
    }

    public function findAll() {

        $objects = array();

        $data_dir = $this->getGlobPath();
        foreach( glob($data_dir) as $file_path ) {
            $object = $this->createObject($file_path);
            $objects[] = $object;
        }

        return $objects;
    }

    public function store($object) {

        $data = array();
        $data["user_id"] = $object->user_id;
        $data["pack_id"] = $object->pack_id;
        $data["date_requested"] = $object->date_requested->format(self::DEFAULT_DATETIME_FORMAT);

        $file_path = $this->getFilePath($object->user_id, $object->pack_id);
        $this->storeToYaml($file_path, $data);
    }

    function createObject($file_path) {
        $yaml = Spyc::YAMLLoad($file_path);
        $date_requested = $this->convertToDateTime($yaml["date_requested"]);
        $object = new MovieViewerDealPackPurchaseRequest($yaml["user_id"], $yaml["pack_id"], $date_requested);

        return $object;
    }

    function getFilePath($user_id, $pack_id) {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/{$pack_id}/{$user_id}_purchase_request.yml";
    }

    function getGlobPath($pack_id) {
        if ($pack_id === "" || $pack_id === NULL) {
            $pack_id = "*";
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/${pack_id}/*_purchase_request.yml";
    }

    function getGlobPathByUser($user_id) {
        if ($user_id === "" || $user_id === NULL) {
            $user_id = "*";
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/*/${user_id}_purchase_request.yml";
    }
}

class MovieViewerDealPackPaymentConfirmationRepositoryInFile extends MovieViewerRepositoryInFile {

    function __construct($settings) {
        parent::__construct($settings);
    }

    public function exists($user_id, $pack_id) {
        $file_path = $this->getFilePath($user_id, $pack_id);

        return file_exists($file_path);
    }

    public function findById($confirmation_id) {
        list($user_id, $pack_id) = mb_split("###", $confirmation_id, 2);

        return $this->findBy($user_id, $pack_id);
    }

    public function findBy($user_id, $pack_id) {

        $file_path = $this->getFilePath($user_id, $pack_id);

        if (!file_exists($file_path)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path));

            throw new MovieViewerRepositoryObjectNotFoundException();
        }

        $object = $this->createObject($file_path);

        return $object;
    }

    public function findByCourse($user_id, $course_id) {

        $objects = array();

        $data_dir = $this->getGlobPathByCourse($user_id, $course_id);

        foreach( glob($data_dir) as $file_path ) {
            $object = $this->createObject($file_path);
            $objects[] = $object;
        }

        return $objects;
    }

    public function findByNotYetStartedUser($user_id) {

        $candidates = $this->findByCourse($user_id, "*");

        $objects = array();
        foreach($candidates as $candidate) {
            if ($candidate->getViewingPeriod()->isBefore()) {
                $objects[] = $candidate;
            }
        }

        return $objects;
    }

    public function findAll() {

        $objects = array();

        $data_dir = $this->getGlobPath();
        foreach( glob($data_dir) as $file_path ) {
            $object = $this->createObject($file_path);
            $objects[] = $object;
        }

        return $objects;
    }

    public function store($object) {

        $data = array();
        $data["user_id"] = $object->user_id;
        $data["pack_id"] = $object->pack_id;
        $data["date_confirmed"] = $object->date_confirmed->format(self::DEFAULT_DATETIME_FORMAT);
        $date["viewing_period"] = array();
        $data["viewing_period"]["date_begin"] = $object->getViewingPeriod()->date_begin->format(self::DEFAULT_DATETIME_FORMAT);
        $data["viewing_period"]["date_end"] = $object->getViewingPeriod()->date_end->format(self::DEFAULT_DATETIME_FORMAT);

        $file_path = $this->getFilePath($object->user_id, $object->pack_id);
        $this->storeToYaml($file_path, $data);
    }

    function createObject($file_path) {
        $yaml = Spyc::YAMLLoad($file_path);
        $date_confirmed = $this->convertToDateTime($yaml["date_confirmed"]);
        $viewing_period = array();
        $viewing_period["date_begin"] = $this->convertToDateTime($yaml["viewing_period"]["date_begin"]);
        $viewing_period["date_end"] = $this->convertToDateTime($yaml["viewing_period"]["date_end"]);
        $object = new MovieViewerDealPackPaymentConfirmation($yaml["user_id"], $yaml["pack_id"], $date_confirmed, $viewing_period);

        return $object;
    }

    function getFilePath($user_id, $pack_id) {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/{$pack_id}/confirmed/{$user_id}_purchase_confirm_payment.yml";
    }

    function getGlobPath($pack_id) {
        if ($pack_id === "" || $pack_id === NULL) {
            $pack_id = "*";
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/${pack_id}/confirmed/*_purchase_confirm_payment.yml";
    }

    function getGlobPathByCourse($user_id, $course_id) {
        if ($user_id === "" || $user_id === NULL) {
            $user_id = "*";
        }
        if ($course_id === "" || $course_id === NULL) {
            $course_id = "*";
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/${course_id}-*/confirmed/{$user_id}_purchase_confirm_payment.yml";
    }
}


?>