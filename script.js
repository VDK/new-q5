
$(document).ready(function() {
    loadCountrySwitch();
});


 // $.getJSON( 'query.php', {
 //    srsearch: listItem.innerText
 //  }).done(function( data ) {
function loadCountrySwitch(){
	
}

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
  	}
  	);
});

function loadRegion(qid){	
	$.getJSON( 'query.php', {
    			qid: qid
  			}).done(function( data ) {
  				$('#region_label').val(data['label']);
  				$('#selected_region').val(data['qid']);
				$('#parent').val(data['parent']);
  				// enable\disable navigation upwards
				if(data['parent'] == null ){
					$('#up_button').hide();
  				}
  				else{
					$('#up_button').show();
  				}
  				//refill dropdown
  				$('#region').empty().append("<option/>");
  				var regions = data['regions'];
  				for(var region in regions){
  					$('#region').append("<option value='"+region+"'>"+regions[region]+"</option>");
  				}
  			});
}
