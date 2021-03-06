<?php

/**
 * Pukiwikiプラグイン::動画視聴 リポジトリ(永続化)
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Repositories
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

require_once PLUGIN_MOVIEVIEWER_PLUGIN_DIR . "/movieviewer/spyc.php";
require_once PLUGIN_MOVIEVIEWER_COMMU_DIR . '/cheetan/db/textsql.php';

function plugin_movieviewer_get_user_repository()
{
    $settings = plugin_movieviewer_get_global_settings();
    return MovieViewerUserRepositoryFactory::createInstance($settings->auth_module, $settings);
}

function plugin_movieviewer_get_courses_repository()
{
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerCoursesRepositoryInFile($settings);
}

function plugin_movieviewer_get_viewing_periods_by_user_repository()
{
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerViewingPeriodsByUserRepositoryInFile($settings);
}

function plugin_movieviewer_get_user_reset_password_token_repository()
{
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerUserResetPasswordTokenRepositoryInFile($settings);
}

function plugin_movieviewer_get_deal_pack_repository()
{
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerDealPackRepositoryInFile($settings);
}

function plugin_movieviewer_get_deal_pack_purchase_request_repository()
{
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerDealPackPurchaseRequestRepositoryInFile($settings);
}

function plugin_movieviewer_get_deal_pack_payment_confirmation_repository()
{
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerDealPackPaymentConfirmationRepositoryInFile($settings);
}

function plugin_movieviewer_get_review_pack_purchase_request_repository()
{
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerReviewPackPurchaseRequestRepositoryInFile($settings);
}

function plugin_movieviewer_get_review_pack_payment_confirmation_repository()
{
    $settings = plugin_movieviewer_get_global_settings();
    return new MovieViewerReviewPackPaymentConfirmationRepositoryInFile($settings);
}

class MovieViewerRepositoryObjectNotFoundException extends Exception
{
}

class MovieViewerRepositoryObjectCantStoreException extends Exception
{
}

class MovieViewerRepositoryInFile
{
    const DEFAULT_DATETIME_FORMAT = "Y-m-d H:i:sP";

    protected $settings = array();

    function __construct($settings)
    {
        $this->settings = $settings;
    }

    protected function storeToYaml($file_path, $data)
    {
        $dir_path = dirname($file_path);

        if (!file_exists($dir_path)) {
            mkdir($dir_path, 0777, true);
        }

        $fp = fopen($file_path, 'w');

        if ($fp === false) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path)
            );

            throw new MovieViewerRepositoryObjectCantStoreException("ファイルオープンに失敗");
        }

        if (!flock($fp, LOCK_EX)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのロックに失敗", array("file" => $file_path)
            );

            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのロックに失敗");
        }

        if (fputs($fp, Spyc::YAMLDump($data)) === false) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルの書きこみに失敗", array("file" => $file_path)
            );

            flock($fp, LOCK_UN);
            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルの書きこみに失敗");
        }

        if (fflush($fp) === false) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのフラッシュに失敗", array("file" => $file_path)
            );

            flock($fp, LOCK_UN);
            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのフラッシュに失敗");
        }

        if (!flock($fp, LOCK_UN)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのロック解除に失敗", array("file" => $file_path)
            );

            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのロック解除に失敗");
        }

        if (!fclose($fp)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのクローズに失敗", array("file" => $file_path)
            );

            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのクローズに失敗");
        }
    }

    protected function convertToDateTime($yaml_value)
    {
        $date_target = new DateTime(null, $this->settings->timezone);
        $date_target->setTimestamp(strtotime($yaml_value));
        return $date_target;
    }
}

class MovieViewerUserRepositoryFactory
{
    static function createInstance($auth_module, $settings)
    {
        if ($auth_module === PLUGIN_MOVIEVIEWER_AUTH_MODULE_COMMU) {
            return new MovieViewerUserRepositoryInCommuDb($settings);
        } else {
            return new MovieViewerUserRepositoryInFile($settings);
        }
    }
}

class MovieViewerUserRepositoryInCommuDb extends MovieViewerRepositoryInFile
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function findById($id)
    {
        if ($this->isAdmin($id)) {
            return $this->createAdmin();
        }

        $db = new CTextDB(PLUGIN_MOVIEVIEWER_COMMU_DIR . "/data/user.txt");
        $result = $db->select("\$email=='{$id}'");

        if (count($result) !== 1) {
            MovieViewerLogger::getLogger()->addError(
                "ユーザが見つからない", array("id" => $id)
            );

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

        if ($data["custom11"] !== "" || $data["custom12"] !== "") {
            $new_routes = new MovieViewerCourseRoutes();
            $this->addCourseRoute($new_routes, $data["custom11"]);
            $this->addCourseRoute($new_routes, $data["custom12"]);
            $object->selected_routes = $new_routes;
        }

        return $object;
    }

    function updateLastLogin($object)
    {
        $db = new CTextDB(PLUGIN_MOVIEVIEWER_COMMU_DIR . "/data/user.txt");
        $db->update(array("last_login" => date(self::DEFAULT_DATETIME_FORMAT)), "\$id=='{$object->commuId}'");
    }
    
    function store($object)
    {
        $db = new CTextDB(PLUGIN_MOVIEVIEWER_COMMU_DIR . "/data/user.txt");
        $db->update(array("id" => $object->commuId, "password" => $object->hashedPassword), "\$id=='{$object->commuId}'");
    }

    function isAdmin($id)
    {
        $db = new CTextDB(PLUGIN_MOVIEVIEWER_COMMU_DIR . "/data/admin.txt");
        $result = $db->select('$id==\'1\'');

        if (count($result) !== 1) {
            return false;
        }

        $adminId = $result[0]['value'];

        return ($adminId === $id);
    }

    function createAdmin()
    {
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

    private function addCourseRoute(&$routes, $courses_combined)
    {
        if ($courses_combined === null || $courses_combined === "") {
            return;
        }
        $courses = split(",", $courses_combined);
        $routes[] = new MovieViewerCourseRoute($courses);
    }
}

class MovieViewerUserRepositoryInFile extends MovieViewerRepositoryInFile
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function findById($id)
    {
        $file_path = $this->getFilePath($id);

        if (!file_exists($file_path)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path)
            );

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

    function store($object)
    {
        $file_path = $this->getFilePath($object->id);

        $fp = fopen($file_path, 'w');

        if ($fp === false) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path)
            );

            throw new MovieViewerRepositoryObjectCantStoreException("ファイルオープンに失敗");
        }

        if (!flock($fp, LOCK_EX)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのロックに失敗", array("file" => $file_path)
            );

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

        if (fputs($fp, Spyc::YAMLDump($data)) === false) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルの書きこみに失敗", array("file" => $file_path)
            );

            flock($fp, LOCK_UN);
            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルの書きこみに失敗");
        }

        if (fflush($fp) === false) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのフラッシュに失敗", array("file" => $file_path)
            );

            flock($fp, LOCK_UN);
            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのフラッシュに失敗");
        }

        if (!flock($fp, LOCK_UN)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのロック解除に失敗", array("file" => $file_path)
            );

            fclose($fp);
            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのロック解除に失敗");
        }

        if (!fclose($fp)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルのクローズに失敗", array("file" => $file_path)
            );

            throw new MovieViewerRepositoryObjectCantStoreException("ファイルのクローズに失敗");
        }
    }

    private function getFilePath($id)
    {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/users/${id}/user.yml";
    }
}

class MovieViewerCoursesRepositoryInFile extends MovieViewerRepositoryInFile
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function find()
    {
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

    private function getFilePath()
    {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/courses.yml";
    }
}

class MovieViewerViewingPeriodsByUserRepositoryInFile extends MovieViewerRepositoryInFile
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function findById($id)
    {
        $yaml = Spyc::YAMLLoad($this->getFilePath($id));
        $object = new MovieViewerViewingPeriodsByUser($id);

        $timezone = plugin_movieviewer_get_global_settings()->timezone;
        foreach ($yaml['viewing_periods'] as $period) {

            $date_begin = new DateTime($period["date_begin"], $timezone);
            $date_end   = new DateTime($period["date_end"], $timezone);

            $period["session_id"] = sprintf("%02d", $period["session_id"]);

            $object->addPeriod(
                $period["course_id"], $period["session_id"],
                $date_begin, $date_end
            );
        }

        return $object;
    }

    function store($object)
    {
        $data = array();
        $data["viewing_periods"] = array();

        foreach ($object->getAllPeriods() as $period) {
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

    private function getFilePath($id)
    {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/users/${id}/viewing_periods.yml";
    }
}

class MovieViewerUserResetPasswordTokenRepositoryInFile extends MovieViewerRepositoryInFile
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function findById($id)
    {
        $data_dir = $this->getDirPath();
        $files = glob("${data_dir}/${id}_*.yml");

        if (count($files) === 0) {
            MovieViewerLogger::getLogger()->addError(
                "トークンが見つからない", array("data_dir" => $data_dir, "token_id" => $id)
            );

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

    function store($object)
    {
        $this->cleanUpToken($object->user_id);

        $data = array();
        $data["id"] = $object->id;
        $data["user_id"] = $object->user_id;
        $data["date_expire"] = $object->date_expire->format(self::DEFAULT_DATETIME_FORMAT);

        $file_path = $this->getFilePath($object->id, $object->user_id);
        $this->storeToYaml($file_path, $data);
    }

    function delete($object)
    {
        $file_path = $this->getFilePath($object->id, $object->user_id);
        unlink($file_path);
    }

    function deleteExpiredTokens($date_target = null)
    {
        // 指定のない場合は現在日時
        if ($date_target == null) {
            $timezone = $this->settings->timezone;
            $date_target = new DateTime(null, $timezone);
        }

        $data_dir = $this->getDirPath();
        foreach (glob("${data_dir}/*.yml") as $file) {
            $yaml = Spyc::YAMLLoad($file);
            $date_expire = $this->convertToDateTime($yaml["date_expire"]);

            if ($date_expire < $date_target) {
                MovieViewerLogger::getLogger()->addInfo(
                    "期限切れのトークンを削除", array("file" => $file, "date_expire" => $yaml["date_expire"])
                );

                unlink($file);
            }
        }
    }

    function cleanUpToken($user_id)
    {
        $data_dir = $this->getDirPath();
        foreach (glob("${data_dir}/*_${user_id}.yml") as $file) {
            unlink($file);
        }
    }

    private function getFilePath($token_id, $user_id)
    {
        $data_dir = $this->getDirPath();
        return "${data_dir}/${token_id}_${user_id}.yml";
    }

    private function getDirPath()
    {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/reset_password";
    }
}

class MovieViewerDealPackRepositoryInFile extends MovieViewerRepositoryInFile
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function findById($pack_id)
    {
        //TODO: DealPackを永続化する方法を検討する
        $container = new MovieViewerS4DealContainer();
        $pack = $container->getPack($pack_id);

        if ($pack === null) {
            MovieViewerLogger::getLogger()->addError(
                "パックが見つからない", array("pack_id" => $pack_id)
            );

            throw new MovieViewerRepositoryObjectCantStoreException();
        }

        return $pack;
    }
}

class MovieViewerDealPackPurchaseRequestRepositoryInFile extends MovieViewerRepositoryInFile
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function findById($request_id)
    {
        list($user_id, $pack_id) = mb_split("###", $request_id, 2);

        return $this->findBy($user_id, $pack_id);
    }

    function findBy($user_id, $pack_id)
    {
        $file_path = $this->getFilePath($user_id, $pack_id);

        if (!file_exists($file_path)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path)
            );

            throw new MovieViewerRepositoryObjectNotFoundException();
        }

        $object = $this->createObject($file_path);

        return $object;
    }

    function findRequestingByUser($user_id)
    {
        $objects = array();

        $repo_conf = plugin_movieviewer_get_deal_pack_payment_confirmation_repository();

        $data_dir = $this->getGlobPathByUser($user_id);
        foreach (glob($data_dir) as $file_path) {
            $object = $this->createObject($file_path);

            if ($repo_conf->exists($object->user_id, $object->pack_id)) {
                continue;
            }

            $objects[] = $object;
        }

        return $objects;
    }

    function findAll()
    {
        $objects = array();

        $data_dir = $this->getGlobPath();
        foreach (glob($data_dir) as $file_path) {
            $object = $this->createObject($file_path);
            $objects[] = $object;
        }

        return $objects;
    }

    function store($object)
    {
        $data = array();
        $data["user_id"] = $object->user_id;
        $data["pack_id"] = $object->pack_id;
        $data["date_requested"] = $object->date_requested->format(self::DEFAULT_DATETIME_FORMAT);

        $file_path = $this->getFilePath($object->user_id, $object->pack_id);
        $this->storeToYaml($file_path, $data);
    }

    private function createObject($file_path)
    {
        $yaml = Spyc::YAMLLoad($file_path);
        $date_requested = $this->convertToDateTime($yaml["date_requested"]);
        $object = new MovieViewerDealPackPurchaseRequest($yaml["user_id"], $yaml["pack_id"], $date_requested);

        return $object;
    }

    private function getFilePath($user_id, $pack_id)
    {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/{$pack_id}/{$user_id}_purchase_request.yml";
    }

    private function getGlobPath($pack_id)
    {
        if ($pack_id === "" || $pack_id === null) {
            $pack_id = "*";
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/${pack_id}/*_purchase_request.yml";
    }

    private function getGlobPathByUser($user_id)
    {
        if ($user_id === "" || $user_id === null) {
            $user_id = "*";
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/*/${user_id}_purchase_request.yml";
    }
}

class MovieViewerDealPackPaymentConfirmationRepositoryInFile extends MovieViewerRepositoryInFile
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function exists($user_id, $pack_id)
    {
        $file_path = $this->getFilePath($user_id, $pack_id);

        return file_exists($file_path);
    }
    
    function findById($confirmation_id)
    {
        list($user_id, $pack_id) = mb_split("###", $confirmation_id, 2);

        return $this->findBy($user_id, $pack_id);
    }

    function findBy($user_id, $pack_id)
    {
        $file_path = $this->getFilePath($user_id, $pack_id);

        if (!file_exists($file_path)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path)
            );

            throw new MovieViewerRepositoryObjectNotFoundException();
        }

        $object = $this->createObject($file_path);

        return $object;
    }

    function findByCourse($user_id, $course_id)
    {
        $objects = array();

        $data_dir = $this->getGlobPathByCourse($user_id, $course_id);

        foreach (glob($data_dir) as $file_path) {
            $object = $this->createObject($file_path);
            $objects[] = $object;
        }

        return $objects;
    }
    
    function findValidsByUser($user_id, $date_target = null)
    {
        if ($date_target === null) {
            $date_target = plugin_movieviewer_now();
        }

        $data_dir = $this->getGlobPathByCourse($user_id, "*");

        $objects = array();
        foreach (glob($data_dir) as $file_path) {
            $object = $this->createObject($file_path);
            if ($object->viewing_period->isBetween($date_target)) {
                $objects[] = $object;
            }
        }

        return $objects;
    }

    function findByNotYetStartedUser($user_id)
    {
        $candidates = $this->findByCourse($user_id, "*");

        $objects = array();
        foreach ($candidates as $candidate) {
            if ($candidate->getViewingPeriod()->isBefore()) {
                $objects[] = $candidate;
            }
        }

        return $objects;
    }

    function findAll()
    {
        $objects = array();

        $data_dir = $this->getGlobPath();
        foreach (glob($data_dir) as $file_path) {
            $object = $this->createObject($file_path);
            $objects[] = $object;
        }

        return $objects;
    }

    function store($object)
    {
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

    private function createObject($file_path)
    {
        $yaml = Spyc::YAMLLoad($file_path);
        $date_confirmed = $this->convertToDateTime($yaml["date_confirmed"]);
        $viewing_period = array();
        $viewing_period["date_begin"] = $this->convertToDateTime($yaml["viewing_period"]["date_begin"]);
        $viewing_period["date_end"] = $this->convertToDateTime($yaml["viewing_period"]["date_end"]);
        $object = new MovieViewerDealPackPaymentConfirmation($yaml["user_id"], $yaml["pack_id"], $viewing_period, $date_confirmed);

        return $object;
    }

    private function getFilePath($user_id, $pack_id)
    {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/{$pack_id}/confirmed/{$user_id}_purchase_confirm_payment.yml";
    }

    private function getGlobPath($pack_id)
    {
        if ($pack_id === "" || $pack_id === null) {
            $pack_id = "*";
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/${pack_id}/confirmed/*_purchase_confirm_payment.yml";
    }

    private function getGlobPathByCourse($user_id, $course_id)
    {
        if ($user_id === "" || $user_id === null) {
            $user_id = "*";
        }
        if ($course_id === "" || $course_id === null) {
            $course_id = "*";
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/deal_pack/${course_id}-*/confirmed/{$user_id}_purchase_confirm_payment.yml";
    }
}

class MovieViewerReviewPackPurchaseRequestRepositoryInFile extends MovieViewerRepositoryInFile
{
    const PATH_DATETIME_FORMAT = "YmdHisO";

    static function convertDateTimeToPathParamater($target)
    {
        if (is_object($target)) {
            $formated_date = $target->format(self::PATH_DATETIME_FORMAT);
        } else {
            $formated_date = $target;
        }
        return $formated_date;
    }

    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function findById($id)
    {
        list($user_id, $date_requested) = mb_split("###", $id, 2);

        return $this->findBy($user_id, $date_requested);
    }

    function findBy($user_id, $date_requested)
    {
        $file_path = $this->getFilePath($user_id, $date_requested);

        if (!file_exists($file_path)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path)
            );

            throw new MovieViewerRepositoryObjectNotFoundException();
        }

        $object = $this->createObject($file_path);

        return $object;
    }

    function findNotYetConfirmed($user_id = null)
    {
        $objects = array();

        $data_dir = $this->getGlobPath($user_id);
        foreach (glob($data_dir) as $file_path) {
            $file_path_confirmed = MovieViewerReviewPackPaymentConfirmationRepositoryInFile::getFilePathFromRequestPath($file_path);
            if (file_exists($file_path_confirmed)) {
                continue;
            }

            $object = $this->createObject($file_path);
            $objects[] = $object;
        }

        return $objects;
    }

    function findAll()
    {
        $objects = array();

        $data_dir = $this->getGlobPath();
        foreach (glob($data_dir) as $file_path) {
            $object = $this->createObject($file_path);
            $objects[] = $object;
        }

        return $objects;
    }

    function store($object)
    {
        $data = $this->serializeObject($object);
        $file_path = $this->getFilePath($object->user_id, $object->getDateRequested());
        $this->storeToYaml($file_path, $data);
    }

    function stash($object)
    {
        $uid = uniqid("", true);
        $data = $this->serializeObject($object);
        $file_path = $this->getFilePathForStash($uid);
        $this->storeToYaml($file_path, $data);
        return $uid;
    }

    function restore($stash_id)
    {
        $file_path = $this->getFilePathForStash($stash_id);

        if (!file_exists($file_path)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path)
            );

            throw new MovieViewerRepositoryObjectNotFoundException();
        }

        $object = $this->createObject($file_path);
        unlink($file_path);
        return $object;
    }

    private function createObject($file_path)
    {
        $yaml = Spyc::YAMLLoad($file_path);
        $date_requested = $this->convertToDateTime($yaml["date_requested"]);

        $item_ids = array();
        foreach($yaml["review_pack"]["items"] as $item) {
            $item_ids[] = $item["course_id"] . "_" . sprintf("%02d", $item["session_id"]);
        }

        $object = new MovieViewerReviewPackPurchaseRequest(
            $yaml["user_id"], 
            $yaml["purchase_method"], 
            $item_ids,
            $date_requested
        );

        return $object;
    }

    private function serializeObject($object)
    {
        $data = array();
        $data["user_id"] = $object->user_id;
        $data["purchase_method"] = $object->purchase_method;
        $data["review_pack"] = array();
        $data["review_pack"]["items"] = array();
        foreach ($object->getItems() as $item) {
            $data_item = array();
            $data_item["course_id"] = $item->course_id;
            $data_item["session_id"] = $item->session_id;
            $data["review_pack"]["items"][] = $data_item;
        }
        $data["date_requested"] = $object->getDateRequested()->format(self::DEFAULT_DATETIME_FORMAT);
        return $data;
    }

    private function getFilePath($user_id, $date_requested)
    {
        $base_dir = $this->settings->data['dir'];
        $formated_date = self::convertDateTimeToPathParamater($date_requested);

        return "${base_dir}/purchase/review_pack/{$user_id}_{$formated_date}_purchase_request.yml";
    }

    private function getFilePathForStash($uid)
    {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/review_pack/_stash/{$uid}.yml";
    }

    private function getGlobPath($user_id = null, $date_requested = null)
    {
        if ($user_id === "" || $user_id === null) {
            $user_id = "*";
        }
        if (is_object($date_requested)) {
            $formated_date = $date_requested->format(self::PATH_DATETIME_FORMAT);
        } else {
            $formated_date = $date_requested;
            if ($formated_date === "" || $formated_date === null) {
                $formated_date = "*";
            }
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/review_pack/{$user_id}_{$formated_date}_purchase_request.yml";
    }

    private function getGlobPathByUser($user_id)
    {
        if ($user_id === "" || $user_id === null) {
            $user_id = "*";
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/review_pack/${user_id}_*_purchase_request.yml";
    }
}

class MovieViewerReviewPackPaymentConfirmationRepositoryInFile extends MovieViewerRepositoryInFile
{
    const PATH_DATETIME_FORMAT = "YmdHisO";

    static function convertDateTimeToPathParamater($target)
    {
        if (is_object($target)) {
            $formated_date = $target->format(self::PATH_DATETIME_FORMAT);
        } else {
            $formated_date = $target;
        }
        return $formated_date;
    }

    static function getFilePathFromRequestPath($request_file_path)
    {
        $file_path = str_replace("_purchase_request.yml", "_purchase_confirm_payment.yml", $request_file_path);
        $file_path = str_replace("/review_pack/", "/review_pack/confirmed/", $file_path);
        return $file_path;
    }

    function __construct($settings)
    {
        parent::__construct($settings);
    }

    function exists($user_id, $date_requested)
    {
        $file_path = $this->getFilePath($user_id, $date_requested);
        return file_exists($file_path);
    }
    
    function findById($confirmation_id)
    {
        list($user_id, $date_requested) = mb_split("###", $confirmation_id, 2);

        return $this->findBy($user_id, $date_requested);
    }

    function findBy($user_id, $date_requested)
    {
        $file_path = $this->getFilePath($user_id, $date_requested);

        if (!file_exists($file_path)) {
            MovieViewerLogger::getLogger()->addError(
                "ファイルオープンに失敗", array("file" => $file_path)
            );

            throw new MovieViewerRepositoryObjectNotFoundException();
        }

        $object = $this->createObject($file_path);

        return $object;
    }

    function findValidsByUser($user_id, $date_target = null)
    {
        if ($date_target === null) {
            $date_target = plugin_movieviewer_now();
        }

        $data_dir = $this->getGlobPathByUser($user_id);

        $objects = array();
        foreach (glob($data_dir) as $file_path) {
            $object = $this->createObject($file_path);
            if ($object->viewing_period->isBetween($date_target)) {
                $objects[] = $object;
            }
        }

        return $objects;
    }

    function findNotYetStartedByUser($user_id)
    {
        $objects = array();

        $data_dir = $this->getGlobPathByUser($user_id);
        foreach (glob($data_dir) as $file_path) {
            $candidate = $this->createObject($file_path);
            if ($candidate->getViewingPeriod()->isBefore()) {
                $objects[] = $candidate;
            }
        }

        return $objects;
    }

    function findAll()
    {
        $objects = array();

        $data_dir = $this->getGlobPath();
        foreach (glob($data_dir) as $file_path) {
            $object = $this->createObject($file_path);
            $objects[] = $object;
        }

        return $objects;
    }

    function store($object)
    {
        $data = array();
        $data["user_id"] = $object->user_id;
        $data["purchase_method"] = $object->purchase_method;
        $data["review_pack"] = array();
        $data["review_pack"]["items"] = array();
        foreach ($object->getItems() as $item) {
            $data_item = array();
            $data_item["course_id"] = $item->course_id;
            $data_item["session_id"] = $item->session_id;
            $data["review_pack"]["items"][] = $data_item;
        }
        $data["date_requested"] = $object->date_requested->format(self::DEFAULT_DATETIME_FORMAT);
        $data["date_confirmed"] = $object->date_confirmed->format(self::DEFAULT_DATETIME_FORMAT);
        $date["viewing_period"] = array();
        $data["viewing_period"]["date_begin"] = $object->getViewingPeriod()->date_begin->format(self::DEFAULT_DATETIME_FORMAT);
        $data["viewing_period"]["date_end"] = $object->getViewingPeriod()->date_end->format(self::DEFAULT_DATETIME_FORMAT);

        $file_path = $this->getFilePath($object->user_id, $object->date_requested);
        $this->storeToYaml($file_path, $data);
    }

    private function createObject($file_path)
    {
        $yaml = Spyc::YAMLLoad($file_path);
        $date_requested = $this->convertToDateTime($yaml["date_requested"]);
        $date_confirmed = $this->convertToDateTime($yaml["date_confirmed"]);
        $item_ids = array();
        foreach ($yaml["review_pack"]["items"] as $item) {
            $item_ids[] = $item["course_id"] . "_" . sprintf("%02d", $item["session_id"]);
        }
        $viewing_period = array();
        $viewing_period["date_begin"] = $this->convertToDateTime($yaml["viewing_period"]["date_begin"]);
        $viewing_period["date_end"] = $this->convertToDateTime($yaml["viewing_period"]["date_end"]);
        $object = new MovieViewerReviewPackPaymentConfirmation(
            $yaml["user_id"],
            $yaml["purchase_method"],
            $item_ids,
            $date_requested,
            $date_confirmed,
            $viewing_period
        );

        return $object;
    }

    private function getFilePath($user_id, $date_requested)
    {
        $base_dir = $this->settings->data['dir'];
        $formated_date = self::convertDateTimeToPathParamater($date_requested);
        return "${base_dir}/purchase/review_pack/confirmed/{$user_id}_{$formated_date}_purchase_confirm_payment.yml";
    }

    private function getGlobPath()
    {
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/review_pack/confirmed/*_purchase_confirm_payment.yml";
    }

    private function getGlobPathByUser($user_id)
    {
        if ($user_id === "" || $user_id === null) {
            $user_id = "*";
        }
        $base_dir = $this->settings->data['dir'];
        return "${base_dir}/purchase/review_pack/confirmed/{$user_id}_*_purchase_confirm_payment.yml";
    }
}

?>