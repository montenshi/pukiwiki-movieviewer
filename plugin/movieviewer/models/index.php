<?php

/**
 * Pukiwikiプラグイン::動画視聴モデルクラスinclude用ファイル
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/vendor/autoload.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . '/spyc.php';

require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/settings.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/logging.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/user.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/course.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/viewing_period.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/mail_builder.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/payment.php";

require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/notifier.php";

require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/dealpack.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/dealpack_payment.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/dealpack_purchase.php";

require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/reviewpack.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/reviewpack_payment.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/reviewpack_purchase.php";

require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/models/aws.php";

?>