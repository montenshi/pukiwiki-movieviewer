$(document).ready(function() {
  var canRequest = function() {
      values = $('[name="sessions"]:checked').map(function(checkbox){
          return $(this).val();
      }).get();

      return ( 1 <= values.length && values.length <= 4 );
  }

  // 初期化
  $('input.movie-session').checkboxradio();
  $('a.ui-button').button({disabled: !canRequest()});

  // 次画面に選択したセッションを引き渡し
  $('a.ui-button').on('click', function(ev){
      values = $('[name="sessions"]:checked').map(function(checkbox){
          return $(this).val();
      }).get();
      base_uri = $(this).attr('href');
      window.location.href = base_uri + '&' + $.param({'items': values.join(',')});
      return false;
  });

  // チェックできる動画の個数を制限する
  $('label').on('click', function(ev){
      values = $('[name="sessions"]:checked').map(function(checkbox){
          return $(this).val();
      }).get();

      target = ev.toElement;
      currentState = $(target).hasClass('ui-state-active') || $(target).hasClass('ui-state-checked');

      numSelected = values.length;
      if (currentState) {
          numSelected--;
      } else {
          numSelected++;
      }

      $('a.ui-button').button({disabled: (numSelected == 0)});

      if (numSelected > 4) {
          ev.preventDefault();
      }
  });

});
