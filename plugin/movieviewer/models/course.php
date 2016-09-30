<?php

/**
 * Pukiwikiプラグイン::動画視聴 コース情報
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.Course
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerCourse
{
    public $id = '';
    public $name = '';
    public $sessions = array();

    function getId()
    {
        return $this->id;
    }

    function getIdShort()
    {
        return substr($this->id, 0, 2);
    }

    function getSession($session_id)
    {
        return $this->sessions[$session_id];
    }

    function describe()
    {
        return $this->name;
    }

    function describeShort()
    {
        return $this->name;
    }

}

class MovieViewerCourses
{

    private $_courses = array();

    function addCourse($course)
    {
        $this->_courses[$course->id] = $course;
    }

    function getCourse($course_id)
    {
        return $this->_courses[$course_id];
    }
}

class MovieViewerSession
{
    public $id = '';
    public $name = '';
    public $chapters = array();

    function getChapter($chapter_id)
    {
        return $this->sessions[$chapter_id];
    }

    function describe()
    {
        return $this->name;
    }

    function describeShort()
    {
        return mb_substr($this->name, 1, mb_strrpos($this->name, "回")-1);
    }
}

class MovieViewerChapter
{
    public $id = '';
    public $name = '';
    public $time = 0;

    function describe()
    {
        return $this->id . ". " . $this->name . " (" . $this->time . "分)";
    }
}

?>