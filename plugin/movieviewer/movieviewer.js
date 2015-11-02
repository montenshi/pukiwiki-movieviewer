$(document).ready(function(){
    document.movieviewer_showMovie = movieviewer_showMovie;

    function movieviewer_showMovie(course_title, session_title, chapter_title, course_id, session_id, chapter_id) {

        var params = { cmd: "movieviewer", ope_type: 'show-movie', course: course_id, session: session_id, chapter: chapter_id };
        $("#myModal_body").load("index.php", params, function(){
            if ($("#my_video_1").size() > 0){
                var myPlayer = videojs('my_video_1');
            }
        });

        var description =
            course_title + "コース " +
            session_title + " " +
            chapter_title;

        $("#myModal").dialog({
            title: description,
            height: 600,
            width:  830,
            modal:  true,
            close: function(event) {
                if ($("#my_video_1").size() > 0){
                    var myPlayer = videojs('my_video_1');
                    myPlayer.pause();
                    myPlayer.dispose();
                }
            }
        });

        return false;
    };

    $('.movieviewer-course-show-chapters').click(function(){
        var listId = $(this).attr('id').replace(/show_chapters/, "list");
        $("#" + listId).toggle();
        return false;
    });

    $('.movieviewer-cource-text-download').click(function(){
        var textIdStr = $(this).attr('id').replace(/_text_download/, "");
        var textIdParams = textIdStr.split('_');

        var params = { cmd: "movieviewer", ope_type: 'download-text', course: textIdParams[0], session: textIdParams[1] };
        location.href = window.movieviewer.baseUrl + "?" + $.param(params);
        return false;
    });

    $("#myModal").hide();
});

