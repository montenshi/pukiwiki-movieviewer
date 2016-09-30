<?php

/**
 * Pukiwikiプラグイン::動画視聴 価検査
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Validators
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

class MovieViewerValidationException extends Exception
{
}

function plugin_movieviewer_validate_csrf_token()
{
    // Tokenがセットされていない場合はエラーにする
    if (!isset($_SESSION['csrf_token'])) {
        throw new MovieViewerValidationException();
    }

    // CSRF対策用トークンはPOSTのcsrf_tokenにあることを前提にする
    $token = filter_input(INPUT_POST, 'csrf_token');

    if ($_SESSION['csrf_token'] !== $token) {
        throw new MovieViewerValidationException();
    }
}

function plugin_movieviewer_validate_user_id($id)
{
    $correct_regex = "^[a-zA-Z0-9_¥-]+$";
    if (mb_ereg($correct_regex, $id)) {
        return;
    }

    if (filter_var($id, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_course_id($id)
{
    $correct_regex = "^[a-zA-Z0-9_¥-]+$";
    if (mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_session_id($id)
{
    $correct_regex = "^[0-9]+$";
    if (mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_chapter_id($id)
{
    $correct_regex = "^[0-9]+$";
    if(mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_deal_pack_id($id)
{
    $correct_regex = "^[a-zA-Z0-9_¥-]+$";
    if (mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_deal_pack_request_id($id)
{
    list($user_id, $deal_pack_id) = explode("###", $id, 2);

    plugin_movieviewer_validate_user_id($user_id);

    plugin_movieviewer_validate_deal_pack_id($deal_pack_id);
}

function plugin_movieviewer_validate_review_pack_formatted_date_requested($value)
{
    $correct_regex = "^[0-9\+]+$";
    if (mb_ereg($correct_regex, $value)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_review_pack_request_id($id)
{
    list($user_id, $formatted_requested_date) = explode("###", $id, 2);

    plugin_movieviewer_validate_user_id($user_id);

    plugin_movieviewer_validate_review_pack_formatted_date_requested($formatted_requested_date);
}

function plugin_movieviewer_validate_purchase_method($id)
{
    $correct_regex = "^[a-zA-Z0-9_¥-]+$";
    if (mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}

function plugin_movieviewer_validate_ymd($id)
{
    $correct_regex = "^\d{4}-\d{1,2}-\d{1,2}$";
    if (mb_ereg($correct_regex, $id)) {
        return;
    }

    throw new MovieViewerValidationException();
}
?>