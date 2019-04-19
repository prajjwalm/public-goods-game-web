$(Document).ready(function () {
	var URL = "back/constructor.php";
	if (1) {	// if loading required
		var data = {
			task: "load",
			gr: "0002"
		};
	}
	$.ajax({
		type:"POST",
		dataType: "json",
		data: data,
		url: URL,
		success: function (data) {
			// alert(JSON.stringify(data));
			var data2 = {
				task: "call",
				gr: "0002"
			}
			$.ajax({
				type:"POST",
				dataType: "json",
				data: data2,
				url: URL,
				success: function (data) {
					// alert(JSON.stringify(data));
				}, 
				error: function (data) {
					// alert(JSON.stringify(data));
				}
			});
		}, error: function (data) {
			// alert(JSON.stringify(data));
		}
	});
});