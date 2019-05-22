//////////////////////////////////////////////////////////////////////////////////////////////// GLOBAL SPACE //////////////////

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function poll_payoff() {
	var url = "back/mp_contrib.php";
	console.log("polling for payoff");
	$.ajax({
		type: "POST",
		url : url,
		data: null,
		dataType: "json",
		success	: function (data) {
			console.log("Poll payoff data: " + JSON.stringify(data));
			if (data['status'] != "error" && data['status'] != "DISASTER" && data['status'] != "nosync") {
				if (data['update'] == 0) {				
				
					// call ok but no new results, continue polling
					poll_payoff();
					
				} else {
					if (data['update'] == 1) {
						
						// some but not all new results obtained, continue polling
						poll_payoff();
						var circles = [];
						for (var i = 0; i < data['new_cases'].length; i++) {
							circles.push(HUMAN_POSITIONS [data['new_cases'][i]['idx']]);
						}
						activeSoc.showCircles(circles);
					} else if (data['update'] == 2) {
						//
						// all results obtained, execute call, stop polling
						//
						let balance_max = parseFloat(data['balance']);
						let carr = {};
						let coarr = {};
						
						for (let i = 0; i < data['carr'].length; i++) {
							carr[data['carr'][i]['idx']] = parseFloat(data['carr'][i]['cash']);
							coarr[data['carr'][i]['idx']] = parseFloat(data['carr'][i]['contrib']);
						}
						
						activeSoc.hideCircles();
						activeSoc.call(coarr, carr, balance_max);
						
						$("#ok").prop('disabled', false);
						$("#contrib").hide();
						$("#rp").show();
						
						if ("rnd" in data) {
							$("#rno").text('' + Math.floor(data['rnd']/2));
						} else {
							console.log("Warning: backend didn't supply round");
							console.log(data)
						}
						
					}
				}
			} else {
				console.log("something bad happened");
			}
		},
		error: function (data) {
			alert("contrib returned bad");
			alert(JSON.stringify(data));
		},
	});
}

function poll_rp() {
	// returns final cash of all players and a 2d array of punishments dealt
	var url = "back/mp_rp.php";
	console.log("polling for rp");
	$.ajax({
		type: "POST",
		url : url,
		data: null,
		dataType: "json",
		success	: function (data) {
			console.log("poll_rp data: " + JSON.stringify(data));
			if (data['status'] != "error" && data['status'] != "DISASTER" && data['status'] != "nosync") {
				if (data['update'] == 0) {				
					//
					// call ok but no new results, continue polling
					//
					poll_rp();
				} else {
					if (data['update'] == 1) {
						//
						// some but not all new results obtained, continue polling
						//
						poll_rp();
						var circles = [];
						for (var i = 0; i < data['new_cases'].length; i++) {
							circles.push(HUMAN_POSITIONS [data['new_cases'][i]['idx']]);
						}
						activeSoc.showCircles(circles);
						
					} else if (data['update'] == 2) {
						//
						// all results obtained, execute rp, stop polling
						//
						activeSoc.hideCircles();
						activeSoc.justice(data['bar']);
						
						$("#ok").prop('disabled', false);
						$("#rp").hide();
						$("#contrib").show();
						
						if ("rnd" in data) {
							$("#rno").text('' + Math.floor(data['rnd']/2));
						} else {
							console.log("Warning: backend didn't supply round");
							console.log(data)
							$("#rno").text('' + (parseInt($("#rno").text()) + 1));
						}
						
					}
				}
			} else {
				if (data['status'] == "nosync") {
					alert("no input expected rn");
				} else {
					alert("an error occured while communicating with the server, please refresh the page");
				}
				console.log("something bad happened");
			}
		},
		error: function (data) {
			alert("rp returned bad");
			alert(JSON.stringify(data));
		},
	});
}


$(function () {
	$("#ok").click(function () {
		var url = "back/mp_game.php";
		
		let rp = {};
		$("#penalties .pen-input").each(function () {
			// rp[($(this).attr('id')).slice(3)] = -(parseFloat($(this).find("input[type=number]").val()) || 0);
			rp[($(this).attr('id')).slice(3)] = (parseFloat($(this).find(".range-slider__value").text()) || 0);
		});
        console.log(rp);
	
        // let contrib = parseFloat($("#cinput").val()) || 0;
        let contrib = parseFloat($("#cinput").text()) || 0;

		let data = {
			contrib: contrib,
			rp: JSON.stringify(rp),
		};
		
		$.ajax({
			type:"POST",
			url: url,
			data: data,
			dataType: "json",
			success: function (data) {
				console.log("game data: " + JSON.stringify(data));
				if (data['type'] == "foo") {
					if (data['all_done']) {
						activeSoc.call(	data['foo']['coarr'], data['foo']['carr'], parseFloat(data['foo']['balance']));						
						$("#contrib").hide();
						$("#rp").show();
						
						if ("rnd" in data) {
							$("#rno").text('' + Math.floor(data['rnd']/2));
						} else {
							console.log("Warning: backend didn't supply round");
							console.log(data)
						}
						
					}
					else {
						$("#ok").prop('disabled', true);
						poll_payoff();
					}
				} else if (data['type'] == "bar") {
					if (data['all_done']) {
						activeSoc.justice(data['bar']);
						$("#rp").hide();
						$("#contrib").show();
						
						if ("rnd" in data) {
							$("#rno").text('' + Math.floor(data['rnd']/2));
						} else {
							console.log("Warning: backend didn't supply round");
							console.log(data);
							$("#rno").text('' + (parseInt($("#rno").text()) + 1));
						}
                        
						
					} else {
						$("#ok").prop('disabled', true);	
						poll_rp();
					}
				}
			},
			error: function (data) {
				alert("game returned bad");
				alert(JSON.stringify(data));
			},
		});
	
		
	});
});