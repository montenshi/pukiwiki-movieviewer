<?php

require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . "/vendor/autoload.php");
require_once(PLUGIN_MOVIEVIEWER_MOVIEVIEWER_DIR . '/spyc.php');
use Aws\CloudFront\CloudFrontClient;
use Aws\Kms\KmsClient;

class MovieViewerAwsCloudFrontUrlBuilder {

    private $cf_settings;

    function __construct($cf_settings) {
        $this->cf_settings = $cf_settings;
    }

    public function buildVideoRTMPUrl($course_id, $session_id, $chapter_id, $duration_to_expire = 10) {
        $expires = time() + $duration_to_expire;
        $path = $this->getVideoRTMPPath($course_id, $session_id, $chapter_id);

        $policy = $this->createPolicy($expires, $path);

        $signed_params = array(
            "url" => "rtmp://{$this->cf_settings['host']['video']['rtmp']}/{$path}",
            "policy" => $policy
        );

        $client = $this->createClient();
        return $client->getSignedUrl($signed_params);
    }
    
    public function buildVideoHLSUrl($course_id, $session_id, $chapter_id, $duration_to_expire = 10) {
        // HLSの場合はSignedURLではなく、暗号化によるコンテンツ保護を行う
        // ので、すのままのURLを返す
        $path = $this->getVideoHLSPath($course_id, $session_id, $chapter_id);
        return "https://{$this->cf_settings['host']['video']['hls']}/{$path}";
    }

    public function buildTextUrl($course_id, $session_id) {
        $expires = time() + 10;
        $path = $this->getTextPath($course_id, $session_id);

        $signed_params = array(
            "url" => "https://{$this->cf_settings['host']['text']}/{$path}",
            "expires" => $expires
        );

        $client = $this->createClient();
        return $client->getSignedUrl($signed_params);
    }

    function createClient() {
        $client_config = array(
            'key'         => $this->cf_settings['signed_url']['credential']['key'],
            'secret'      => $this->cf_settings['signed_url']['credential']['secret'],
            'private_key' => $this->cf_settings['signed_url']['key_pair']['private_key'],
            'key_pair_id' => $this->cf_settings['signed_url']['key_pair']['key_pair_id']
        );
        return CloudFrontClient::factory($client_config);
    }

    function createPolicy($expires, $path) {
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

    function getVideoRTMPPath($course_id, $session_id, $chapter_id) {
        $course_dir = substr($course_id, 0, 2);
        return "courses/{$course_dir}/{$course_id}{$session_id}_{$chapter_id}.mp4";
    }

    function getVideoHLSPath($course_id, $session_id, $chapter_id) {
        $course_dir = substr($course_id, 0, 2);
        $base = "{$course_id}{$session_id}_{$chapter_id}";
        return "out/{$course_dir}/{$base}/{$base}.m3u8";
    }

    function getTextPath($course_id, $session_id) {
        $course_dir = substr($course_id, 0, 2);
        return "courses/{$course_dir}/{$course_id}{$session_id}.zip";
    }
}

class MovieViewerAwsKMSClient {
    
    private $kms_settings;

    function __construct($kms_settings) {
        $this->kms_settings = $kms_settings;
    }

    public function decrypt($target, $encryption_context) {
        $client = KmsClient::factory(array(
            'key'    => $this->kms_settings['credential']['key'],
            'secret' => $this->kms_settings['credential']['secret'],
            'region' => $this->kms_settings['region']
        ));
        
        $result = $client->decrypt(array(
            'CiphertextBlob'    => base64_decode($target),
            'EncryptionContext' => $encryption_context
        ));
        
        return $result;
    }
    
}

class MovieViewerAwsTranscorderEncriptionKeyDecypter {
    
    private $kms_settings;
    private $tc_settings;

    function __construct($kms_settings, $tc_settings) {
        $this->kms_settings = $kms_settings;
        $this->tc_settings = $tc_settings;
    }
    
    public function execute() {
        $client = new MovieViewerAwsKMSClient($this->kms_settings);
        
        $result = $client->decrypt($this->tc_settings['encryption_key'], array("service" => "elastictranscoder.amazonaws.com"));
        
        return $result['Plaintext'];
    }

}

?>