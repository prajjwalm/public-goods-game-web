<?php 
	session_start();
	if (empty($_SESSION['grid']) || empty($_SESSION['uid'])) {
		session_destroy();
	} else {
		// move to game room
		header("Location: multiplayer.php");
	}
?>

<!DOCTYPE html>

<!-- The 'peeps' used are taken from ncase.me/trust (https://github.com/ncase/trust/tree/gh-pages/assets) -->
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name = "viewport" content = "width=device-width, initial-scale=1">
		
		<meta name = "keywords" content = "Public Goods Game, Simulation, IIT, Kanpur">
		<meta name = "Description" content = "A simulation of the public goods game">
		<meta name = "theme-color" content = "#103d87">
		
		<title>PGG</title>
		
		<!-- js libraries -->
		<script src = "js/lib/jquery-3.2.1.min.js"></script>
		<script src="js/lib/pixi.min.js"></script>
		<script src="js/lib/jquery-ui-1.12.1.custom/jquery-ui.min.js"></script>
		
		<!-- styles -->
		<link href="assets/icons/fa/css/all.min.css" rel="stylesheet">
		<link rel="stylesheet" href="js/lib/jquery-ui-1.12.1.custom/jquery-ui.min.css">
		
		<!-- my style -->
		<link type="text/css" rel="stylesheet" href="css/build/index.css" />
		
		<!-- my code -->
		<script src = "js/index_ui.js"></script>
		<script src = "js/index_gen_room.js"></script>
	</head>

	<body>
		<div id = "input">
			<!-- div class = "input-row header">User Settings:</div -->
			<div class = "input-row header mode"> Play Mode:</div>
			<div class = "input-row opaque mode" style = "height: auto; min-height: 48px; padding-left: 32px; padding-right: 32px;">
				<div id = "gametype">
					<label class="switch">
						<span class="word">Exploration</span>
						<input type="checkbox" id = "mode">
						<span class="slider round"></span>
					</label>
				</div>
				<div id = "gameroom" class="main-input" autocomplete = "off" style = "display:none;">
					<div class="group">
						<input type="text" id="grinput" placeholder="&nbsp;" required maxlength="8" pattern="[a-z0-9]{8}"/>
						<label for="grinput">Game Room Id</label>
						<div class="bar"></div>
					</div>
				</div>			
				<div id = "name" class="main-input" autocomplete = "off" style = "display:none;">
					<div class="group">
						<input type="text" id="nameinput" placeholder="&nbsp;" required maxlength="32" pattern="^[a-zA-Z0-9-_\x20]{2,32}$"/>
						<label for="nameinput">Name</label>
						<div class="bar"></div>
					</div>
				</div>			
			</div>
			<div class = "input-row description mode">
				<span></span>
			</div>
			<div class = "input-row button"  style = "height: 32px;">
				<button id = "start">Start</button>
				<button id = "gen" style = "display:none;" disabled> Generate </button>
				<button id = "join" style = "display:none;" disabled>Join</button>
			</div>
		</div>
	</body>
</html>