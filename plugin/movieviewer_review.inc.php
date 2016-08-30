<?php

require_once("movieviewer.ini.php");

function plugin_movieviewer_review_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_review_convert(){

    $user_id = plugin_movieviewer_get_auth_manager()->getUserId();

    $current_user = plugin_movieviewer_get_user_repository()->findById($user_id);
    $viewing_periods = plugin_movieviewer_get_viewing_periods_by_user_repository()->findById($user_id);

    $hsc = "plugin_movieviewer_hsc";

    # 期限の切れているものをリストアップ
    $viewing_periods = $viewing_periods->getExpiredPeriods();

    # コースごとに仕分け
    $viewing_periods_by_course = array();
    $current_course_id = '';
    foreach ($viewing_periods as $period) {
        if ($current_course_id !== $period->course_id) {
            $viewing_periods_by_course[$period->course_id] = array();
            $current_course_id = $period->course_id;
        }
        $viewing_periods_by_course[$period->course_id][] = $period;
    }
    
    $courses = plugin_movieviewer_get_courses_repository()->find();
    $content_courses = "";
    foreach ($viewing_periods_by_course as $course_id => $periods) {

        $course = $courses->getCourse($course_id);

        $content_periods = "";
        foreach ($periods as $period) {
            $session = $course->getSession($period->session_id);
            $field_id = "{$hsc($course->id)}_{$hsc($session->id)}";
            $content_periods .=<<<TEXT
            <label for="{$field_id}">{$session->describe()}</label>
            <input class='movie-session' type="checkbox" name="sessions" id="{$field_id}" value="{$field_id}">
TEXT;
        }

        $content_course =<<<TEXT
        <h2>{$hsc($course->name)}</h2>
        <fieldset style='border: none;'>
        {$content_periods}
        </fieldset>
TEXT;

        $content_courses .= $content_course;
    }

    $uri_start_bank = plugin_movieviewer_get_script_uri() . "?%3A動画配信会員_再視聴申し込み_銀行振り込み&purchase_method=bank";
    $uri_start_credit = plugin_movieviewer_get_script_uri() . "?%3A動画配信会員_再視聴申し込み_クレジットカード&purchase_method=credit";

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.0/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.12.0/themes/cupertino/jquery-ui.css" rel="stylesheet">
    <script src="plugin/movieviewer/assets/js/movieviewer.js"></script>
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    <style>
    label { margin: 2px 5px; }
    span.ui-checkboxradio-icon.ui-corner-all.ui-icon.ui-icon-background.ui-icon-check.ui-state-checked { background-image: url("https://code.jquery.com/ui/1.12.0/themes/cupertino/images/ui-icons_2694e8_256x240.png"); }
    .ui-checkboxradio-label { width: 6em; }
    .ui-checkboxradio-label.ui-button { text-align: left; }
    </style>

    再視聴したいコースを選択して、申し込みボタンを押してください。

    ${content_courses}

    <div style="margin-top:10px;">
        <a href="${uri_start_bank}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>銀行振り込みで申し込み</a>
        <a href="${uri_start_credit}" class='ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only'>クレジットカードで申し込み</a>
    </div>

    <script>
      $(document).ready(function() {
        $('input.movie-session').checkboxradio();
        $('a.ui-button').button({disabled: true});
        $('a.ui-button').on('click', function(ev){
            values = $('[name="sessions"]:checked').map(function(checkbox){
                return $(this).val();
            }).get();
            base_uri = $(this).attr('href');
            window.location.href = base_uri + '&' + $.param({'sessions': values.join(',')});
            return false;
        });
        $('label').on('click', function(ev){
            values = $('[name="sessions"]:checked').map(function(checkbox){
                return $(this).val();
            }).get();

            target = ev.toElement;
            currentState = $(target).hasClass('ui-state-active') || $(target).hasClass('ui-state-checked');

            numSelected = values.length;
            if (currentState) {
                numSelected--;
            } else {
                numSelected++;
            }

            if (numSelected == 0){
                $('a.ui-button').button({disabled: true});
                return;
            } else {
                $('a.ui-button').button({disabled: false});
            }

            if (numSelected > 4) {
                ev.preventDefault();
            }
        });
      });
    </script>

TEXT;

    return $content;
}

function plugin_movieviewer_review_action(){
    return "hoge";
}

?>