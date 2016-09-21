<?php

// ログの出力 plugins/movieviewer に出力される(10日分)
class MovieViewerLogger {
    static $logger = null;

    public static function getLogger() {
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