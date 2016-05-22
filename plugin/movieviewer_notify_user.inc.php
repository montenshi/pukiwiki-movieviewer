<?php

require_once("movieviewer.ini.php");
require_once("movieviewer_purchase_start.inc.php");

function plugin_movieviewer_notify_user_init() {
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_notify_user_convert(){

    try {
        $user = plugin_movieviewer_get_current_user();
    } catch (MovieViewerRepositoryObjectNotFoundException $ex) {
        return '';
    }

    if ($user->isAdmin()) {
        return '';
    }

    $page_args = func_get_args();
    $params = array();
    $params['start_page_bank']   = $page_args[0];
    $params['start_page_credit'] = $page_args[1];
    $params['back_page'] = $page_args[2];

    global $defaultpage;
    if (!isset($params['back_page'])) {
        $params['back_page'] = $defaultpage;
    }

    $notifiers = array();
    $notifiers[] = new MovieViewerPurchaseOfferNotifier();
    $notifiers[] = new MovieViewerPurchaseStatusNotifier();
    $notifiers[] = new MovieViewerReportNotifier();
    
    $messages = array();
    foreach($notifiers as $notifier) {
        $message = $notifier->generateMessage($user, $params);
        if ($message !== "") {
            $messages[] = $message;
        }
    }
    
    if (count($messages) === 0) {
        return '';
    }
    
    $messages_flat = implode("\r\n", $messages);

    $content =<<<TEXT
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <link href="https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css" rel="stylesheet">
    <link href="plugin/movieviewer/assets/css/movieviewer.css" rel="stylesheet">
    <h2>お知らせ</h2>
    <div class="movieviewer-notices">
    $messages_flat
    </div>
TEXT;

    return $content;
}

?>
