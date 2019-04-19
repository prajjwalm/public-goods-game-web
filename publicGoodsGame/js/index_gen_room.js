//////////////////////////////////////////////////////////////////////////////////////////////// GLOBAL SPACE //////////////////

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$(function () {
	$("#gen").click(function () {		
		var url = "back/index_gen.php";
		var name = $("#name input[type=text]").val();
		if (!(/^[a-zA-Z0-9-_\x20]{2,32}$/.test(name))) return;		
		var data = {
			name: name,
		}
		$.ajax({
			type:"POST",
			url: url,
			data: data,
			dataType: "json",
			success: function (data) {
				if (data['status'] === "perfect") {
					window.location.replace("multiplayer.php");
				} else console.log(JSON.stringify(data));
			},
			error: function (data) {
				console.log("bad");
				console.log(JSON.stringify(data));
			},
		});
	});
	
	$("#join").click(function () {
		var grId = $("#gameroom input[type=text]").val();
		var name = $("#name input[type=text]").val();
		
		if (!(/^[a-z0-9]{8}$/.test(grId))) return;
		if (!(/^[a-zA-Z0-9-_\x20]{2,32}$/.test(name))) return;		
		
		var url = "back/index_join.php";
		var data = {
			gr: grId,
			name: name,
		};
		$.ajax({
			type: "POST",
			url: url,
			data: data,
			dataType: "json",
			success: function (data) {
				if (data['status'] === "perfect") {
					window.location.replace("multiplayer.php");
				} else if (data['status'] === "wrong") {
					alert("Couldn't join room: Either it doesn't exist, or the game has already begun");
				} else if (data['status'] === "error") {
					if (data['msg'].includes("add query failure")) {
						alert("user name already taken");
						console.log(JSON.stringify(data));
					}
				}
				console.log(data);
			},
			error: function (data) {
				console.log("bad");
				console.log(JSON.stringify(data));
			},
			
		});
	});
});