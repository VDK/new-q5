const image_root = "http://commons.wikimedia.org/wiki/Special:FilePath/";

$(document).ready(function(){
  // CITOID MAGIC
  $('#ref_url').on('change', function(){
    const url = encodeURI($('#ref_url').val());
    $('#ref_params').addClass('spindle');
    $('#ref_title, #ref_lang, #ref_authors, #ref_pubdate').attr('readonly', true);

    $.getJSON('query.php', {
      url: url
    }).done(function(data){
      $('#ref_params').removeClass('spindle');
      $('#ref_title, #ref_lang, #ref_authors, #ref_pubdate').attr('readonly', false);

      $('#ref_url').val(data['url']);
      $('#ref_title').val(data['title']);
      $('#ref_lang').val(data['language']);
      $('#ref_authors').val(data['authors']);
      $('#ref_pubdate').val(data['pubdate']);
    }).fail(function(){
      $('#ref_params').removeClass('spindle');
      $('#ref_title, #ref_lang, #ref_authors, #ref_pubdate').attr('readonly', false);
    });
  });

  // Match with existing Wikidata item
  $('#fullname').on('change', function(){
    $.getJSON('query.php', {
      srsearch: $(this).val()
    }).done(function(data){
      if(data !== "nee"){
        $("#possible_match").show();
        $('#responses').empty();
        const new_item = $('<li>', { class: "block-2 selected", text: "New item" }).click(selectOption);
        $('#responses').append(new_item);

        data.forEach(function(item){
          const option = $('<li>', { class: "block-2 option" });
          if(item['image']){
            option.css('background', "url('"+image_root+item['image']+"?width=100') no-repeat left top");
            option.attr('image', true);
          }
          option.append("<a href='https://wikidata.org/wiki/"+item['qitem']+"' target='_blank'>"+item['itemLabel']+"</a>");
          if(item['dateOfBirth'] || item['dateOfDeath']){
            option.append(" (");
            if(item['dateOfBirth']){
              const dob = new Date(item['dateOfBirth']);
              option.append(dob.getFullYear());
            }
            if(item['dateOfDeath']){
              const dod = new Date(item['dateOfDeath']);
              option.append("-"+ dod.getFullYear());
            }
            option.append(")");
          }
          if(item['occupation']){
            option.append(", "+ item['occupation']);
          }
          if(item['country']){
            option.append(" from "+ item['country']);
          }
          option.attr('qid', item['qitem']).click(selectOption);
          $('#responses').append(option);
        });
      }
    });
  });

  $('#age, #qs').on('keyup', function(){
    this.value = this.value.replace(/[^0-9]/gi, '');
    if($(this).attr('id') === 'qs'){
      this.value = this.value.replace(/https:\/\/www\.wikidata\.org\/wiki\/(Property:)?/, '');
    }
  });
});

// Select match with existing Wikidata item
function selectOption(){
  $('#person_QID').val($(this).attr('qid'));
  const selected_option = $('#responses').find('.selected')[0];
  $(selected_option).removeClass('selected').addClass('option');
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
