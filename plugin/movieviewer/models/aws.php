<?php

/**
 * Pukiwikiプラグイン::動画視聴 AWS操作
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.Aws
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/vendor/autoload.php";
require_once PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . '/spyc.php';

use Aws\CloudFront\CloudFrontClient;
use Aws\Kms\KmsClient;

class MovieViewerAwsCloudFrontUrlBuilder
{
    private $_cf_settings;

    function __construct($cf_settings)
    {
        $this->_cf_settings = $cf_settings;
    }

    function buildVideoRTMPUrl($course_id, $session_id, $chapter_id, $duration_to_expire = 10)
    {
        $expires = time() + $duration_to_expire;
        $path = $this->_getVideoRTMPPath($course_id, $session_id, $chapter_id);

        $policy = $this->_createPolicy($expires, $path);

        $signed_params = array(
            "url" => "rtmp://{$this->_cf_settings['host']['video']['rtmp']}/{$path}",
            "policy" => $policy
        );

        $client = $this->_createClient();
        return $client->getSignedUrl($signed_params);
    }
    
    function buildVideoHLSUrl($course_id, $session_id, $chapter_id, $duration_to_expire = 10)
    {
        // HLSの場合はSignedURLではなく、暗号化によるコンテンツ保護を行う
        // ので、すのままのURLを返す
        $path = $this->_getVideoHLSPath($course_id, $session_id, $chapter_id);
        return "https://{$this->_cf_settings['host']['video']['hls']}/{$path}";
    }

    function buildTextUrl($course_id, $session_id)
    {
        $expires = time() + 10;
        $path = $this->_getTextPath($course_id, $session_id);

        $signed_params = array(
            "url" => "https://{$this->_cf_settings['host']['text']}/{$path}",
            "expires" => $expires
        );

        $client = $this->_createClient();
        return $client->getSignedUrl($signed_params);
    }

    private function _createClient()
    {
        $client_config = array(
            'key'         => $this->_cf_settings['signed_url']['credential']['key'],
            'secret'      => $this->_cf_settings['signed_url']['credential']['secret'],
            'private_key' => $this->_cf_settings['signed_url']['key_pair']['private_key'],
            'key_pair_id' => $this->_cf_settings['signed_url']['key_pair']['key_pair_id']
        );
        return CloudFrontClient::factory($client_config);
    }

    private function _createPolicy($expires, $path)
    {
        $policy = <<<POLICY
        {
            "Statement": [
                {
                    "Resource": "{$path}",
                    "Condition": {
                        "DateLessThan": {"AWS:EpochTime": {$expires}}
                    }
                }
            ]
        }
POLICY;
        return $policy;
    }

    private function _getVideoRTMPPath($course_id, $session_id, $chapter_id)
    {
        $course_dir = substr($course_id, 0, 2);
        return "courses/{$course_dir}/{$course_id}{$session_id}_{$chapter_id}.mp4";
    }

    private function _getVideoHLSPath($course_id, $session_id, $chapter_id)
    {
        $course_dir = substr($course_id, 0, 2);
        $base = "{$course_id}{$session_id}_{$chapter_id}_HLSv3_1500";
        return "courses/{$course_dir}/video/{$base}/{$base}.m3u8";
    }

    private function _getTextPath($course_id, $session_id)
    {
        $course_dir = substr($course_id, 0, 2);
        return "courses/{$course_dir}/{$course_id}{$session_id}.zip";
    }
}

class MovieViewerAwsKMSClient
{
    private $_kms_settings;

    function __construct($kms_settings)
    {
        $this->_kms_settings = $kms_settings;
    }

    function decrypt($target, $encryption_context)
    {
        $client = KmsClient::factory(
            array(
                'key'    => $this->_kms_settings['credential']['key'],
                'secret' => $this->_kms_settings['credential']['secret'],
                'region' => $this->_kms_settings['region']
            )
        );
        
        $result = $client->decrypt(
            array(
                'CiphertextBlob'    => base64_decode($target),
                'EncryptionContext' => $encryption_context
            )
        );
        
        return $result;
    }
    
}

class MovieViewerAwsTranscorderEncriptionKeyDecypter
{
    
    private $_kms_settings;
    private $_tc_settings;

    function __construct($kms_settings, $tc_settings)
    {
        $this->_kms_settings = $kms_settings;
        $this->_tc_settings = $tc_settings;
    }
    
    function execute()
    {
        $client = new MovieViewerAwsKMSClient($this->_kms_settings);
        
        $result = $client->decrypt(
            $this->_tc_settings['encryption_key'],
            array("service" => "elastictranscoder.amazonaws.com")
        );
        
        return $result['Plaintext'];
    }

}

?>