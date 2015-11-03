<?php

class MovieViewerValidationException extends Exception {}

function plugin_movieviewer_validate_user_id($id) {

    $correct_regex = "^[a-zA-Z0-9_¥-]+$";
    if(mb_ereg($correct_regex, $id)) {
        return;
    }

    if(filter_var($id, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_course_id($id) {

    $correct_regex = "^[a-zA-Z0-9_¥-]+$";
    if(mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_session_id($id) {

    $correct_regex = "^[0-9]+$";
    if(mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_chapter_id($id) {

    $correct_regex = "^[0-9]+$";
    if(mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_deal_pack_id($id) {

    $correct_regex = "^[a-zA-Z0-9_¥-]+$";
    if(mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_deal_pack_request_id($id) {

    list($user_id, $deal_pack_id) = explode("###", $id, 2);

    plugin_movieviewer_validate_user_id($user_id);

    plugin_movieviewer_validate_deal_pack_id($deal_pack_id);
}

function plugin_movieviewer_validate_purchase_method($id) {

    $correct_regex = "^[a-zA-Z0-9_¥-]+$";
    if(mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}

?>