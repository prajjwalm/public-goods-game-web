
$( function() {
	for (var i = 0 ; i < 4; i++){
		$( "#playground .api #control .pop_slider:eq("+i+")" ).css({
			"top": "70vh",
			"left": "" + (800 + 50 * i) + "px"
		});
		$( "#playground .api #control .pop_count:eq("+i+")" ).css({
			"top": "67vh",
			"left": "" + (800 + 50 * i) + "px"
		});
	}
	for (var i = 0 ; i < 4; i++){
		let j = i;
		$( "#playground .api #control .pop_slider:eq("+j+")" ).slider({
		  orientation: "vertical",
		  range: "min",
		  min: 0,
		  max: 8,
		  value: POP_CNTS[i],
		  slide: function( event, ui ) {
			$( "#playground .api #control .pop_count:eq("+j+")" ).val( ui.value );
				POP_CNTS[j] = ui.value;
				if (!_pop_refresh_reqd) {
					warn("Restart Required");
					_pop_refresh_reqd = true;
				}
		  }
		});
		$( "#playground .api #control .pop_count:eq("+j+")" ).val( $( "#playground .api #control .pop_slider:eq("+j+")" ).slider( "value" ) );
	}
	
	$( "#playground .api #control .man_slider" ).css({
		"position": "absolute",
		"top": "56vh",
		"left": "750px"
	});
	$( "#playground .api #control .man_slider" ).slider({
		  range: "min",
		  min: 0,
		  max: 100,
		  value: 50,
		  slide: function( event, ui ) {
			$( "#playground .api #control .api" ).val((ui.value * REF / 100).toFixed(2));
			$( "#playground .api #control .api_true" ).val( ui.value );
		  }
		});
	$( "#playground .api #control .api" ).val( $( "#playground .api #control .man_slider" ).slider( "value" ) * REF / 100 );
	$( "#playground .api #control .api_true" ).val( $( "#playground .api #control .man_slider" ).slider( "value" ) );
});