<?php

/**
 * Pukiwikiプラグイン::動画視聴 メール送信
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.Mail
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerMailBuilder
{
    protected $settings;

    function __construct($settings)
    {
        $this->settings = $settings;
    }

    function createMail($mail_to)
    {
        $mail = new PHPMailer();
        $mail->IsHTML(false);
        
        $mail->IsSMTP();
        $mail->Host = $this->settings->smtp["host"];
        $mail->SMTPAuth = $this->settings->smtp["smtp_auth"];
        if (isset($this->settings->smtp["encryption_protocol"])) {
            $mail->SMTPSecure = $this->settings->smtp["encryption_protocol"];
        }
        $mail->Port = $this->settings->smtp["port"];

        $mail->Username = $this->settings->smtp["user"];
        $mail->Password = $this->settings->smtp["password"]; 
        $mail->CharSet = $this->settings->smtp["charset"];

        $mail->SetFrom($this->settings->smtp["from"]);
        $mail->From = $this->settings->smtp["from"];
        $mail->AddAddress($mail_to);

        $mail->SMTPDebug = 0;
        if (isset($this->settings->smtp["debug"])) {
            $mail->SMTPDebug = 1;
        }

        return $mail;
    }

    function renderBody($template, $params)
    {
        $regex = '/{{\s*([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*}}/s';
        return preg_replace_callback(
            $regex, 
            function ($m) use ($params) {
                if (!isset($params[$m[1]])) {
                    return '';
                }

                return $params[$m[1]];
            },
            $template
        );
    }
}

class MovieViewerResetPasswordMailBuilder extends MovieViewerMailBuilder
{
    function __construct($settings)
    {
        parent::__construct($settings);
    }

    public function build($mail_to, $reset_url)
    {
        $settings_local = $this->settings->template["reset_password"];
        $mail = $this->createMail($mail_to);

        $body = $this->renderBody($settings_local["body"], array('reset_url' => $reset_url));

        $mail->Subject = $settings_local["subject"];
        $mail->Body = $body;
        return $mail;
    }
}

?>