function getRandomInt(max) {
  return Math.floor(Math.random() * Math.floor(max));
}

$(function () {	
	var jmpTime = 10000 + getRandomInt(2000);
	var intervalID = window.setInterval(myCallback, jmpTime);

	function myCallback() {
		var url = "back/ping.php";
		$.ajax({
			type: "POST",
			url : url,
			data: null,
			dataType: "json",
			success	: function (data) {
				// console.log(JSON.stringify(data));
				if (data['alt_rows']) {
					// refresh the page
					window.location.reload();
				}
			},
			error: function (data) {
				alert(JSON.stringify(data));
			},
		});
	}

});