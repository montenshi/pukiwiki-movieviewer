<?php

/**
 * Pukiwikiプラグイン::動画視聴 ログ出力
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.Logging
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerLogger
{
    static $logger = null;

    public static function getLogger()
    {
        if (self::$logger === null) {
            $log_path = PLUGIN_MOVIEVIEWER_LOG_DIR . "/movieviewer.log";
            self::$logger = new \Monolog\Logger('movieviewer');
            self::$logger->pushHandler(new \Monolog\Handler\RotatingFileHandler($log_path, 10, \Monolog\Logger::INFO));
            $ip = new \Monolog\Processor\IntrospectionProcessor(
                \Monolog\Logger::DEBUG,
                array(
                'Monolog\\',
                'Illuminate\\',
                )
            );
            self::$logger->pushProcessor($ip);
        }
        return self::$logger;
    }
}

?>