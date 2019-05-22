<?php
	session_start();
	if (empty($_SESSION['grid'])) {
		session_destroy();
		header("Location: index.php");
	}
	
	$_SESSION['last_file'] = "multiplayer";
	if (isset($_SESSION['times'])){
		unset($_SESSION['times']);
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
		
		<!-- my style >
		<link type="text/css" rel="stylesheet" href="css/style.css" /-->
		<link type="text/css" rel="stylesheet" href="css/build/game.css" />
		<link type="text/css" rel="stylesheet" href="css/build/tooltip.css" />
		<link type="text/css" rel="stylesheet" href="css/build/slider.css" />
		
		<!-- my code -->
		<script src = "js/base.js"></script>
		<script src = "js/slider_init.js"></script>
		<script src = "js/slider.js"></script>
		<script src = "js/mp_renderer.js"></script>
		<script src = "js/mp_game.js"></script>
		<script src = "js/mp_players.js"></script>
		<script>		
		function KeyPress(e) {
			var evtobj = window.event? event : e;
			if (evtobj.keyCode == 90 && evtobj.ctrlKey && evtobj.shiftKey) {
				$.ajax({
					type: "POST",
					url: "back/showsession.php",
					data: null,
					dataType: "text",
					success: function (data) {
						alert(JSON.stringify(data));
					},
					error: function (data) {
						alert("bad");
						alert(JSON.stringify(data));
					},
				});
			} else if (evtobj.keyCode === 13 && !evtobj.ctrlKey && !evtobj.shiftKey && GAMEON) {
                $("#ok").click();
            }
		}

		document.onkeydown = KeyPress;
		</script>
		
		<script src = "js/ping.js"></script>
	</head>

	<body>
	<div id = "main">
		<div id = "gamezone">
			<div id = "header" style = "z-index: 1;">
				Room  <span id = "grno"><?php echo $_SESSION['grid'] ?></span>: Round No.<span id = "rno"><?php echo strval(intdiv($_SESSION['rno'] ,2)) ?></span>
                <span id="roomid" style = "display:none;"><?php echo $_SESSION['roomid']?></span>
                <div class="tooltip" id ="header-info"><div class="info">
                    Ask your friends to enter the 8-char code following 'Room' and then click 'Join' to enter this room, once they do, they will show in the 
                    Players list in the 
					<?php if ($_SESSION['host'] === true): ?>
                        ADMIN space.
					<?php else: ?>
                        Meta Data.
					<?php endif ?>
                </div></div>
			</div>						
			<div id = "playground">
				<!-- canvas -->
                <div class="tooltip" id ="canvas-info" style="display:none;"><div class="info">
                    The large black cash values over a member's head represent his current cash, the smaller shaded values beside them 
                    his/her last contribution (this will be absent if no last contribution is available, such as before the first round).
                    The shaded text below these values is the name that they entered on joining.
                </div></div>
				<div id = "premsg">
					<?php if ($_SESSION['host'] === true): ?>
					Once all the players have joined, adjust the Bot populations as reqd
					and press start to begin the game. Note: no player/bot can be added once the game starts.
					<?php else: ?>
					Waiting for the head to start the game.
					<?php endif ?>
				</div>
				<div id = "balance"></div>
				<div id = "names" class = "dynamic"></div>
			</div>
			<div id = "game-interface" style = "visibility:hidden;">
				<!-- User Inputs -->
				<div style = "display:none;" id = "rp">
					<div id = "rp-info" style = "display: flex; justify-content: space-evenly;">Reward/Penalize: 
                        <div class="tooltip"><div class="info">
                        Adjust the sliders to reward/penalize a person. Positive values (moving the slider right) stand for rewards, 
                        and the target's cash will increase by that amount, your own decresing by 120% of that. Negetive values (moving
                        it left) penalize the target and will cause the target's cash to decrease by that amount and your's by 20% of that.
                        Shifting your own slider will have no effect. You can move as many sliders as you want.
                        </div></div>
                    </div>
					<div id = "penalties"></div> <!-- Container for all the penalty inputs, keep empty -->
				</div>
                <div id = "contrib">
                Contribution
                    <div class="range-slider">
                      <input class="range-slider__range" type="range" value="0" min="0" max="100" step="1">
                      <span class="range-slider__value" id = "cinput">0</span>
                    </div>
                </div>
				<button id = "ok">OK</button>
			</div>
		</div>
		<div id = "etc">
		
			<?php if ($_SESSION['host'] === true): ?>
			
				<div id = "meta" class = "opaque">
					<div id = "admin-header" style = "display:flex; justify-content: space-evenly; align-items:center;">
                        <p>ADMIN space</p>
                        <div class="tooltip"><div class="info">
                            To begin the game click on the Start button below, note
                            that no player/bot may be added after the game has begun.
                        </div></div>
                    </div>
					<div id = "pop-manager">
                        <div id = "pop-manager-header"  style = "display:flex; justify-content: space-between; align-items:center;">
						&nbsp;&nbsp;Bot Populations:
                        <div class="tooltip"><div class="info">
                            Adjust the sliders to fix the bot populations, including bots
                            is entirely optional (clicking start with all sliders to zero
                            begins the game without any bots). The available categories are,
                            respectively, moderately poor contributers, high contributers.
                        </div></div>
                        </div>
						<div id = "hatspace"></div>
						<div id = "botpops">
							<input type="text" class="pop-count" readonly>
							<input type="text" class="pop-count" readonly>
							<input type="text" class="pop-count" readonly>
							<input type="text" class="pop-count" readonly>
							<input type="text" class="pop-count" readonly>
							<div id = "sliders">
								<div class = "pop-slider" style = "height: 120px;"></div>
								<div class = "pop-slider" style = "height: 120px;"></div>
								<div class = "pop-slider" style = "height: 120px;"></div>
								<div class = "pop-slider" style = "height: 120px;"></div>
								<div class = "pop-slider" style = "height: 120px;"></div>
							</div>
						</div>
					</div>
					<div id = "player-manager">
                        <div id = "player-manager-header"  style = "display:flex; justify-content: space-between; align-items:center;margin-bottom:4px;">
						Players Participating:
                        <div class="tooltip"><div class="info">
                            This shows the players currently in the gameroom, wait for 
                            everyone to join before starting the game, as no new players
                            may be added afterwords. Should a player disconnect, he/ she
                            will have around 30s to reestablish connection, failing which
                            he/she shall be removed from the game. <br/>
                            PS. the crown icon in the player list denotes the host, he/she
                            alone can start the game, but will have no advantages once the 
                            game begins
                        </div></div>
                        </div>
						<div id = "players">
							<div class = "admin player" style = "order: 1;"><span class = "name"><?php echo $_SESSION['name']?></span><i class="fas fa-crown"></i></div>
						</div>
					</div>
					<div id = "meta-controls" style = "display:flex; justify-content:space-evenly;">
						<button id = "start">Start</button>
						<button id = "end" style = "display:none;">End</button>
					</div>
				</div>
			<?php else: ?>
				<div id = "meta" class = "opaque">
					<p>Meta Data</p>
					<div id = "pop-manager">
                        <div id = "pop-manager-header"  style = "display:flex; justify-content: space-between; align-items:center;">
						&nbsp;&nbsp;Bot Populations:
                        <div class="tooltip"><div class="info">
                            Once the game begins, this section will show the number of bots 
                            participating per category. This population division is decided by the host.
                        </div></div>
                        </div>
						<div id = "hatspace"></div>
						<div id = "botpops-view">
							<input type="text" class="pop-count" readonly>
							<input type="text" class="pop-count" readonly>
							<input type="text" class="pop-count" readonly>
							<input type="text" class="pop-count" readonly>
							<input type="text" class="pop-count" readonly>
						</div>
					</div>
					<div id = "player-manager">
                        <div id = "player-manager-header"  style = "display:flex; justify-content: space-between; align-items:center;margin-bottom:4px;">
						Players Participating:
                        <div class="tooltip"><div class="info">
                            This shows the players currently in the gameroom.
                            Should a player disconnect, he/she will have around
                            30s to reestablish connection, failing which he/she 
                            shall be removed from the game. <br/>
                            PS. the crown icon in the player list denotes the host, he/she
                            alone can start the game, but will have no advantages once the 
                            game begins
                        </div></div>
                        </div>
						<div id = "players">
						</div>
					</div>
				</div>
			<?php endif ?>
			
			<!-- div id = "leaderboard" class = "opaque"> leaderboard </div>
			<div id = "balance_graph" class = "opaque"> graph </div -->
		
		</div>
	</div>	

	<!-- div id = "msg-container">
		<div id = "warning" style = "Display:None;"><i class= "fas fa-exclamation-triangle"></i><span></span></div>
		<div id = "info" style = "Display:None;"><span></span></div>
	</div -->

	</body>
</html>