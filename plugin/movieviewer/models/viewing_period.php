<?php

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

    public static function sortByCourse($periods) {
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

?>