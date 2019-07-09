//////////////////////////////////////////////////////////////////////////////////////////////// GLOBAL SPACE //////////////////


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$( function() {
	for (var i = 0 ; i < 5; i++){
		$( "#meta #botpops .pop-slider:eq("+i+")" ).css({
			"left": "" + (18 + 36 * i) + "px"
		});
		$( "#meta #botpops .pop-count:eq("+i+")" ).css({
			"left": "" + (18 + 36 * i) + "px"
		});
	}
	for (var i = 0 ; i < 5; i++){
		$( "#meta #botpops-view .pop-count:eq("+i+")" ).css({
			"left": "" + (18 + 36 * i) + "px"
		});
	}
	for (var i = 0 ; i < 5; i++){
		let j = i;
		$( "#meta #botpops .pop-slider:eq("+j+")" ).slider({
		  orientation: "vertical",
		  range: "min",
		  min: 0,
		  max: 8,
		  value: POP_CNTS[i],
		  slide: function( event, ui ) {
			$( "#meta #botpops .pop-count:eq("+j+")" ).val( ui.value );
				POP_CNTS[j] = ui.value;
		  }
		});
		$( "#meta #botpops .pop-count:eq("+j+")" ).val( $( "#meta #botpops .pop-slider:eq("+j+")" ).slider( "value" ) );
	}
	
	$("#meta #botpops .pop-slider:gt(1)").slider("disable");
});