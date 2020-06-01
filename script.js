$( document ).ready(function(){
	// ltrHack();
});

$(function(){
    //navigate up and down regions affected by COVID-19
  	$('#up_button').on('click', function () {
  		if($('#parent').val().match(/^Q\d+$/)){
  			loadRegion($('#parent').val());
  		}
  	});
  	$('#region').on('change', function () {
  		if($('#region').val().match(/^Q\d+$/)){
  			loadRegion ($('#region').val());
  		}
  	});
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
            if (item['image']){
              option.innerHTML += "<img class='response_image' src ='"+item['image']+"' alt='"+item['itemLabel']+"'>" + option.innerHTML;
            }
            option.innerHTML += "<a href='https://wikidata.org/wiki/"+item['qitem']+"' target='_blank'>"+item['itemLabel']+"</a>";
            if(item['dateOfBirth']){
              var d = new Date(item['dateOfBirth']);
              option.innerHTML += " ("+ d.getFullYear() +")";
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
});
//select match with existing Wikidata item
function selectOption(){
  $('#person_QID').val($(this).attr('qid'));
  var a = $('#responses').find('.selected')[0];
  $(a).removeClass('selected');
  $(a).addClass('option');
  $(this).addClass('selected');
  $(this).removeClass('option');
}

function loadRegion(qid){	
	$.getJSON( 'query.php', {
			qid: qid
		}).done(function( data ) {
			$('#region_label').text(data['label']);
			$('#selected_region').val(data['qid']);
		$('#parent').val(data['parent']);
			// enable\disable navigation upwards
		if(data['parent'] == null ){
			$('#up_button').hide();
		}
		else{
		  $('#up_button').show();
		}
		if (data['regions'].length == 0){
			$('#region_selection').hide();
		}
		else{
			$('#region_selection').show();
			//refill dropdown
			$('#region').empty().append("<option/>");
			var regions = data['regions'];
			for(var region in regions){
				$('#region').append("<option value='"+region+"'>"+regions[region]+"</option>");
			}
		}
	});
}
function sendQS(){
  var qs  = $('#quickstatement').val().trim();
  qs = qs.split(/\n/).join('||');
  qs = encodeURIComponent(qs);
  var url = "https://tools.wmflabs.org/quickstatements/#v1="+qs;
  var win = window.open(url, '_blank');
  win.focus();
}