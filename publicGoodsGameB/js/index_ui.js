//////////////////////////////////////////////////////////////////////////////////////////////// GLOBAL SPACE //////////////////

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$(function () {	

	// checkbox handler
	function checkBoxHandler () {
		var txt, word;
		var id = $(".switch input[type=checkbox]").attr('id');
		if ($(".switch input[type=checkbox]").prop('checked')) {
			txt = "Multiplayer";
			word = "To play the public goods game, you must enter into a gameroom. If one of your friends has already generated a gameroom \
            where the game hasn't started yet, ask him for the (8char) room id and enter it into the 'Game Room Id' input. Otherwise create a fresh \
            gameroom by clicking generate. Note: to enable either buttons a 2-10 lettered name must be entered, which will be your id in the game";
			$("#join").show();
			$("#gen").show();
			$("#gameroom").show();
			$("#name").show();
			$("#start").hide();
		} else {
			txt = "Exploration";
			word = "UNDER CONSTRUCTION. Select multiplayer mode";
			$("#join").hide();
			$("#gen").hide();
			$("#gameroom").hide();
			$("#name").hide();
			$("#start").show();
			
		}
		$(".switch input[type=checkbox]").siblings(".word").text(txt);
		$(".tooltip .info").text(word);
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


