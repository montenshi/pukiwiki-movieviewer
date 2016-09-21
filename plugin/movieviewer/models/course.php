<?php

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

?>