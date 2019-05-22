//////////////////////////////////////////////////////////////////////////////////////////////// GLOBAL SPACE //////////////////

var HUMAN_PLAYERS = [];
var HUMAN_POSITIONS = {};


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function poll() {	
	var url = "back/mp_players.php";

	if (!GAMEON){
		$.ajax({
			type:"POST",
			url: url,
			data: null,
			dataType: "json",
			success: function (data) {				
				console.log(JSON.stringify(data));
				// adjust the player log
				var arr = data["arr"];
				arr.forEach(function (elmt) {
					elmt['idx'] = parseInt(elmt['idx']);
				});
				arr.sort(function (a, b) {
					return a['idx'] - b['idx'];
				});
				if ($("#meta-controls").length) { // if admin				
					arr.forEach(function (elmt) {
						if (!HUMAN_PLAYERS.includes(elmt['idx'])) {
							HUMAN_POSITIONS[elmt['idx']] = HUMAN_PLAYERS.length;		// obvious but still, do not move this line down
							HUMAN_PLAYERS.push(elmt['idx']);
							$("#players").append('<div class = "player" style = "order: '+elmt['idx']+';"><span class = "name">'+elmt['name']+'</span></div>');	// <i class="fas fa-user-slash"></i>
						}
					});
				} else {
					arr.forEach(function (elmt) {
						if (!HUMAN_PLAYERS.includes(elmt['idx'])) {
							HUMAN_POSITIONS[elmt['idx']] = HUMAN_PLAYERS.length;
							HUMAN_PLAYERS.push(elmt['idx']);
							if (parseInt(elmt['host'])) {
								$("#players").append('<div class = "player" style = "order: '+elmt['idx']+';"><span class = "name">'+elmt['name']+'</span><i class="fas fa-crown"></i></div>');
							} else {
								$("#players").append('<div class = "player" style = "order: '+elmt['idx']+';"><span class = "name">'+elmt['name']+'</span></div>');
							}
						}
					});
				}
				if (data["mrnd"]) {
					//
					// note: an int rather than a string, so no parseInt reqd				
					// game started, no more joining, render the canvas and the names and the initial cash
					//
					// the canvas renderer just needs the number of participants
					// the names will be in the order of index, and will be linked to the cash
					//
					GAMEON = true;
					for (var i = 0; i < POP_CNTS.length; i++) POP_CNTS[i] = 0;
					//
					// once mainstream, remove these calculations, don't want all to know the types
					//
					for (var idx in data['players']) {
						if (data['players'].hasOwnProperty(idx)) {
							switch (data['players'][idx]['type']) {
							case 'x':
								POP_CNTS[0] ++;
								break;
							case 'b':
								POP_CNTS[1] ++;
								break;
							case 'g':
								POP_CNTS[2] ++;
								break;
							case 'a':
								POP_CNTS[3] ++;
								break;
							case 'c':
								POP_CNTS[4] ++;
								break;
							case 'r':
								POP_CNTS[5] ++;
								break;
							default:
								alert("unrecognized prototype");
								break;
							}
						}
					}
					var total_pop = POP_CNTS.reduce((a,b) => a + b, 0);
				
					$("#premsg").hide();
					$("#game-interface").css('visibility', 'visible');
					$("#start").prop('disabled', true);

					$("#meta #botpops .pop-slider").slider("disable");
					$("#rno").text(Math.floor(data['mrnd'] / 2));
					
					
					renderGame(total_pop, data['players']);
					
					$("#botpops-view input.pop-count").each(function () {
						$(this).val(POP_CNTS[$(this).index() + 1]);
					});

					$("#botpops input.pop-count").each(function () {
						$(this).val(POP_CNTS[$(this).index() + 1]);
					});
					
					if (data['mrnd'] % 2 == 1) {
						if (data['srnd'] % 2 == 1) {
							if (data['srnd'] != data['mrnd']) console.log("deadly error: srnd = " + data['srnd'] + ", mrnd = " + data['mrnd'] + ", exp: s = m");
							$("#contrib").show();
							$("#rp").hide();
							$("#ok").prop('disabled', false);
						} else {
							if (data['srnd'] != data['mrnd'] + 1) console.log("deadly error: srnd = " + data['srnd'] + ", mrnd = " + data['mrnd'] + ", exp: s = m + 1");
							$("#contrib").show();
							$("#rp").hide();
							$("#ok").prop('disabled', true);
							poll_payoff();
						}
					} else {
						if (data['srnd'] % 2 == 1) {
							if (data['srnd'] != data['mrnd'] + 1) console.log("deadly error: srnd = " + data['srnd'] + ", mrnd = " + data['mrnd'] + ", exp: s = m + 1");
							$("#contrib").hide();
							$("#rp").show();
							$("#ok").prop('disabled', true);
							poll_rp();
						} else {
							if (data['srnd'] != data['mrnd']) console.log("deadly error: srnd = " + data['srnd'] + ", mrnd = " + data['mrnd'] + ", exp: s = m");
							$("#contrib").hide();
							$("#rp").show();
							$("#ok").prop('disabled', false);
						}
					}
					
					return;
				}
				else {
					poll();
					return;
				}
			},
			error: function (data) {
				console.log("bad");
				console.log(JSON.stringify(data));
			},
		});
	}
}



$(function () {
    // put backend verification ?
	if ($("#meta-controls").length) { // if admin, admin's idx is guaranteed to be 1
		HUMAN_POSITIONS[1] = 0;
		HUMAN_PLAYERS.push(1);
		poll();
	} else {		
		poll();
	}
	$("#start").click(function () {
		var url = "back/mp_start.php";
		var data = {
			nb: POP_CNTS[0],
			ng: POP_CNTS[1],
			na: POP_CNTS[2],
			nc: POP_CNTS[3],
			nr: POP_CNTS[4],
		};
		$.ajax({
			type:"POST",
			url: url,
			data: data,
			dataType: "json",
			success: function (data) {
				console.log(JSON.stringify(data));	
			},
			error: function (data) {
				alert("bad start");
				console.log(JSON.stringify(data));
			},
		});
	});
});


