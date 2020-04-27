$( document ).ready(function(){
	// ltrHack();
});


$(function(){
  	$('#region').on('change', function () {
  		if($('#region').val().match(/^Q\d+$/)){
  			loadRegion ($('#region').val());
  		}
  	});
  	$('#up_button').on('click', function () {
  		if($('#parent').val().match(/^Q\d+$/)){
  			loadRegion($('#parent').val());
  		}
  	});
  	$('#ref_url').on('change', function(){
  		var url = encodeURI($('#ref_url').val());
      $('#ref_params').addClass('spindle');
  		$('#ref_url').attr('readonly', true);
		  $('#ref_title').attr('readonly', true);
		  $('#ref_lang').attr('readonly', true);
		  $('#ref_authors').attr('readonly', true);
		  $('#ref_pubdate').attr('readonly', true);
      $('#submit').prop('disabled', true);
  		$.getJSON( 'query.php', {
    			url: url
  			}).done(function( data ) {
          $('#ref_params').removeClass('spindle');
          $('#ref_url').attr('readonly', false);
          $('#ref_title').attr('readonly', false);
          $('#ref_lang').attr('readonly', false);
          $('#ref_authors').attr('readonly', false);
          $('#ref_pubdate').attr('readonly', false);
          $('#submit').prop('disabled', false);

          $('#ref_url').val(data['url']);
          $('#ref_title').val(data['title']);
          $('#ref_lang').val(data['language']);
          $('#ref_authors').val(data['authors']);
          $('#ref_pubdate').val(data['pubdate']);
  		});
  	});
});

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
