<?php
# 目的: ページに動画を埋め込む
# 引数: コースID、セッションID、チャプターID
#       カンマの後に空白は入れないこと
# 例) #movieviewer_embed("GDGuide","01","01")

require_once("movieviewer.ini.php");

function plugin_movieviewer_embed_init(){
    plugin_movieviewer_set_global_settings();
}

function plugin_movieviewer_embed_convert(){

    $videoopts = func_get_args();

    $settings = MovieViewerSettings::loadFromYaml(PLUGIN_MOVIEVIEWER_PATH_TO_SETTINGS);
    $cf_settings = $settings->aws['cloud_front'];

    $builder = new MovieViewerAwsCloudFrontUrlBuilder($cf_settings);
    $signed_path_rtmp = $builder->buildVideoRTMPUrl($videoopts[0], $videoopts[1], $videoopts[2], 24 * 60 * 60);
    $signed_path_hls = $builder->buildVideoHLSUrl($videoopts[0], $videoopts[1], $videoopts[2], 24 * 60 * 60);

    $base_uri = plugin_movieviewer_get_base_uri();

    // videojs-contrib-hls.min.js は Firefox on Mac用 (現時点では)
    $embed = <<<EOC
    <link href="http://vjs.zencdn.net/5.4/video-js.css" rel="stylesheet">
    <video id="my_video_1" class="video-js vjs-default-skin vjs-big-play-centered" preload="auto" controls width="550" height="319" 
           data-setup='{"techOrder":["flash","html5"]}'>
        <source src="rtmp://{$cf_settings['host']['video']['rtmp']}/cfx/st/&mp4:{$signed_path_rtmp}" type="rtmp/mp4">
        <source src="{$signed_path_hls}" type="application/x-mpegURL">
    </video>
    <p>
    最大化ボタン <img src="$base_uri/plugin/movieviewer/assets/images/button-maximize.png"> は再生ボタン <img src="$base_uri/plugin/movieviewer/assets/images/button-play.png"> を押した後、表示されます。
    </p>
    <script src="https://vjs.zencdn.net/5.4/video.js"></script>
    <script src="$base_uri/plugin/movieviewer/assets/js/videojs-contrib-hls.min.js"></script>    
EOC;

    return $embed;
}
?>