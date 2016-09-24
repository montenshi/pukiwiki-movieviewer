<?php

/**
 * Pukiwikiプラグイン::動画視聴 視聴期限
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.ViewingPeriod
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerUserResetPasswordToken
{
    public $id;
    public $user_id;
    public $date_exipire;

    function __construct($user_id)
    {
        $this->id = hash("md5", mt_rand());
        $this->user_id = $user_id;
        $this->date_expire = plugin_movieviewer_now()->add(new DateInterval('PT1H'));
    }

    function isValid($date_target = null)
    {
        // 指定のない場合は現在日時
        if ($date_target == null) {
            $timezone = plugin_movieviewer_get_global_settings()->timezone;
            $date_target = new DateTime(null, $timezone);
        }

        if ($this->date_expire >= $date_target) {
            return true;
        }

        return false;
    }
}

class MovieViewerPeriod
{
    public $date_begin;
    public $date_end;

    function __construct($date_begin, $date_end)
    {
        $this->date_begin = $date_begin;
        $this->date_end = $date_end;
    }

    function isBefore($date_target = null)
    {
        if ($date_target === null) {
            $date_target = plugin_movieviewer_now();
        }

        return ($this->date_begin > $date_target);
    }

    function isBetween($date_target = null)
    {
        if ($date_target === null) {
            $date_target = plugin_movieviewer_now();
        }

        return ($this->date_begin <= $date_target && $date_target <= $this->date_end);
    }

    function isExpired($date_target = null)
    {
        if ($date_target === null) {
            $date_target = plugin_movieviewer_now();
        }

        return ($this->date_end < $date_target);
    }

    function aboutToExpire($date_target = null)
    {
        if ($date_target === null) {
            $date_target = plugin_movieviewer_now();
        }

        if ($this->isExpired($date_target)) {
            return false;
        }

        $date_calc = new DateTime($date_target->format('Y-m-d H:i:sP'));
        
        return ($date_calc->add(new DateInterval('P1M')) >= $this->date_end);
    }
}

class MovieViewerViewingPeriod
{
    static function sortByCourse($periods)
    {
        $viewing_periods_by_course = array();
        $current_course_id = '';
        foreach ($periods as $period) {
            if ($current_course_id !== $period->course_id) {
                $viewing_periods_by_course[$period->course_id] = array();
                $current_course_id = $period->course_id;
            }
            $viewing_periods_by_course[$period->course_id][] = $period;
        }
        return $viewing_periods_by_course;
    }

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

    function isExpired($target)
    {
        $timezone = plugin_movieviewer_get_global_settings()->timezone;
        $target_dateonly = new DateTime($target->format('Y-m-d'), $timezone);

        if ($this->date_end < $target_dateonly) {
            return true;
        }

        return false;
    }

    function isValid($target)
    {
        $timezone = plugin_movieviewer_get_global_settings()->timezone;
        $target_dateonly = new DateTime($target->format('Y-m-d'), $timezone);

        if (($this->date_end >= $target_dateonly) && ($this->date_begin <= $target_dateonly)) {
            return true;
        }

        return false;
    }

    function getDurationToEnd($target)
    {
        $timezone = plugin_movieviewer_get_global_settings()->timezone;
        $target_dateonly = new DateTime($target->format('Y-m-d'), $timezone);

        return $this->date_end->diff($target_dateonly);
    }
}

class MovieViewerViewingPeriodsByUser
{
    public $user_id;
    private $_periods = array();

    function __construct($user_id)
    {
        $this->user_id = $user_id;
    }

    function getValidPeriods($date_target = null)
    {
        // 指定のない場合は現在日時
        if ($date_target == null) {
            $timezone = plugin_movieviewer_get_global_settings()->timezone;
            $date_target = new DateTime(null, $timezone);
        }

        $objects = array();

        foreach ($this->_periods as $period) {
            if ($period->isValid($date_target)) {
                $objects[] = $period;
            }
        }

        return $objects;
    }

    function getExpiredPeriods($date_target = null)
    {
        // 指定のない場合は現在日時
        if ($date_target == null) {
            $timezone = plugin_movieviewer_get_global_settings()->timezone;
            $date_target = new DateTime(null, $timezone);
        }

        $objects = array();

        foreach ($this->_periods as $period) {
            if ($period->isExpired($date_target)) {
                $objects[] = $period;
            }
        }

        return $objects;
    }

    function getAllPeriods()
    {
        $objects = array();

        foreach ($this->_periods as $period) {
            $objects[] = $period;
        }

        return $objects;
    }

    function addPeriod($course_id, $session_id, $date_begin, $date_end)
    {
        $period = new MovieViewerViewingPeriod($course_id, $session_id, $date_begin, $date_end);
        $this->_periods[$this->getKey($course_id, $session_id)] = $period;
    }

    function canView($course_id, $session_id, $date_target = null)
    {
        // 指定のない場合は現在日時
        if ($date_target == null) {
            $timezone = plugin_movieviewer_get_global_settings()->timezone;
            $date_target = new DateTime(null, $timezone);
        }

        $period = $this->_periods[$this->getKey($course_id, $session_id)];

        if ($period == null) {
            return false;
        }

        return $period->isValid($date_target);
    }

    private function getKey($course_id, $session_id)
    {
        return $course_id . ":" . $session_id;
    }
}

?>