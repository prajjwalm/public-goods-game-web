//////////////////////////////////////////////////////////////////////////////////////////////// GLOBAL SPACE //////////////////

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$(function () {	

	// checkbox handler
	function checkBoxHandler () {
		var txt, word;
		var id = $(".switch input[type=checkbox]").attr('id');
		if ($(".switch input[type=checkbox]").prop('checked')) {
			txt = "Multiplayer";
			word = "Choose this mode to play the public goods game with friends. The name is just for others to recognize you during the \
			match and doesn't require any login. (Click generate to create a new room to play in, or join to join an existing room where \
			the game hasn't yet started.)";
			$("#join").show();
			$("#gen").show();
			$("#gameroom").show();
			$("#name").show();
			$("#start").hide();
		} else {
			txt = "Exploration";
			word = "Choose this mode if you are curious about what we can learn from the public goods game or simply wish to play \
			the game in a single/zero player mode. (Under construction)";
			$("#join").hide();
			$("#gen").hide();
			$("#gameroom").hide();
			$("#name").hide();
			$("#start").show();
			
		}
		$(".switch input[type=checkbox]").siblings(".word").text(txt);
		$("#input .description."+id+" span").text(word);
	}
	
	
	
	// join button handler
	function checkValid() {
		if ($("#name input:valid").length) {
			$("#gen").prop('disabled', false);
			if ($("#gameroom input:valid").length) {
				$("#join").prop('disabled', false);
			} else {
				$("#join").prop('disabled', true);
			}
		} else {
			$("#gen").prop('disabled', true);
			$("#join").prop('disabled', true);
		}
	}
	
	$("#grinput").change(function () {
		checkValid();
	});
	$("#grinput").keyup(function () {
		checkValid();
	});
	
	$("#nameinput").change(function () {
		checkValid();
	});
	$("#nameinput").keyup(function () {
		checkValid();
	});
	
	$(".switch input[type=checkbox]").click(function () {
		checkBoxHandler();
	});
	
	// Initial Begin
	checkBoxHandler();
});


