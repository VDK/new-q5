var image_root = "http://commons.wikimedia.org/wiki/Special:FilePath/";
$(function(){
    //CITOID MAGIC
    $('#ref_url').on('change', function(){
      var url = encodeURI($('#ref_url').val());
      $('#ref_params').addClass('spindle');
      $('#ref_title').attr('readonly', true);
      $('#ref_lang').attr('readonly', true);
      $('#ref_authors').attr('readonly', true);
      $('#ref_pubdate').attr('readonly', true);
      $.getJSON( 'query.php', {
          url: url
        }).done(function( data ) {
          $('#ref_params').removeClass('spindle');
          $('#ref_title').attr('readonly', false);
          $('#ref_lang').attr('readonly', false);
          $('#ref_authors').attr('readonly', false);
          $('#ref_pubdate').attr('readonly', false);

          $('#ref_url').val(data['url']);
          $('#ref_title').val(data['title']);
          $('#ref_lang').val(data['language']);
          $('#ref_authors').val(data['authors']);
          $('#ref_pubdate').val(data['pubdate']);
      }).fail(function(){
        $('#ref_params').removeClass('spindle');
          $('#ref_title').attr('readonly', false);
          $('#ref_lang').attr('readonly', false);
          $('#ref_authors').attr('readonly', false);
          $('#ref_pubdate').attr('readonly', false);
      });
    });
    //match with existing Wikidata item
    $('#fullname').on('change', function(){
      $.getJSON( 'query.php', {
        srsearch: $(this).val()
      }).done(function( data ) {
        if (data != "nee"){
          $("#possible_match").show();
          $('#responses').empty();
          var new_item = document.createElement('li');
          new_item.className = "block-2 selected";
          new_item.innerHTML = "New item";
          new_item.onclick = selectOption;
          $('#responses').append(new_item);
          for (var i = 0; i <= data.length - 1; i++) {
            var item = data[i];
            var option = document.createElement('li');
            option.className = "block-2 option";
            option.innerHTML = "";
            if (item['image']) {
              var imageUrl = image_root + encodeURIComponent(item['image']) + "?width=100";
              option.style.backgroundImage = "url('" + imageUrl + "')";
              option.style.backgroundRepeat = "no-repeat";
              option.style.backgroundPosition = "left top";
              option.setAttribute('image', true);
            }
            option.innerHTML += "<a href='https://wikidata.org/wiki/"+item['qitem']+"' target='_blank'>"+item['itemLabel']+"</a>";
            if(item['dateOfBirth'] || item['dateOfDeath']){
              option.innerHTML += " (";
              if (item['dateOfBirth']){
                var d = new Date(item['dateOfBirth']);
                option.innerHTML += d.getFullYear();

              }
              if(item['dateOfDeath']){
                var d = new Date(item['dateOfDeath']);
                option.innerHTML += "-"+ d.getFullYear();
              }
              option.innerHTML += ")"
            }
            if(item['occupation']){
              option.innerHTML += ", "+ item['occupation'] ;
            }
            if(item['country']){
              option.innerHTML += " from "+ item['country'] ;
            }
            option.setAttribute('qid', item['qitem']);
            option.onclick = selectOption;
            $('#responses').append(option);
          }
        }
      });
    });
    $('#age').on('keyup', function(){
      this.value = this.value.replace(/[^0-9]/gi, '');
    });
    $('#qs').on('keyup', function(){
      https://www.wikidata.org/wiki/Q7411
      this.value = this.value.replace(/https:\/\/www\.wikidata\.org\/wiki\/(?:Property:)?/, '');
    });
});
//select match with existing Wikidata item
function selectOption() {
  $('#person_QID').val($(this).attr('qid'));
  const previouslySelected = $('#responses').find('.selected')[0];
  $(previouslySelected).removeClass('selected').addClass('option');
  $(this).addClass('selected').removeClass('option');
}


function sendQS(){
  let qs  = $('#quickstatement').val().trim();
  qs = qs.split(/\n/).join('||');
  qs = encodeURIComponent(qs);
  const url = "https://quickstatements.toolforge.org/#v1="+qs;
  const win = window.open(url, '_blank');
  win.focus();
}

$(document).ready(function() {
  $("#close").click(function() {
    $("#notice").fadeOut();
    $("#reopen").fadeIn();
    sendActionToController('close'); // Send action 'close' to controller
  });

  $("#reopen").click(function() {
    $("#notice").fadeIn();
    $("#reopen").fadeOut();
    sendActionToController('reopen'); // Send action 'reopen' to controller
  });

  function sendActionToController(action) {
    $.ajax({
      type: "GET",
      url: "notice_controller.php", // Replace with the actual URL of your PHP controller file
      data: { action: action },
      success: function(response) {
        console.log("Action sent to controller: " + action);
      },
      error: function(xhr, status, error) {
        console.error("Error sending action to controller: " + error);
      }
    });
  }
});