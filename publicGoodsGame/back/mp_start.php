<?php
	// To diasable direct url access
    if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );
        die( header( 'location: ../index.php' ) );
    }

	session_start();
	$_SESSION['last_file'] = "mp_start";
	
	$grid = $_SESSION['grid'];
	$uuid = $_SESSION['uuid'];
	$host = $_SESSION['host'];
	
	if ($host) {
		//
		//  might also want to verify that uuid matches that stored in the meta table
		//
		$return = $_POST;
		$return['uuid'] = $uuid;
		$return['grid'] = $grid;
		
		$nb = intval($_POST['nb']);
		$ng = intval($_POST['ng']);
		$na = intval($_POST['na']);
		$nc = intval($_POST['nc']);
		$nr = intval($_POST['nr']);
		
		$inputOk = preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $uuid) && preg_match("/^[a-z0-9]{8}$/", $grid);
		
		if ($inputOk) {
			require_once("path.php");
			
			$cxn = new mysqli($hostname, $username,  $password, $dbname);
			
			if ($cxn->connect_errno) {
				$return['status'] = "error";
				$return['msg'] = "connection error: " . $cxn->connect_error;
				exit(json_encode($return));
			}
			$chkQuery = "SELECT `rnd` FROM `meta` WHERE `grid` = '$grid';";
			if ($result = $cxn->query($chkQuery)) {
				$row = $result->fetch_assoc();
				$result->close();
				if ($row['rnd'] == 0) {					
					//
					// add the requested number of bots to the room 
					// set the graphics for each member
					// start the game(i.e the first round)
					//					
					$N = $nb + $ng + $na + $nc + $nr;
					$q = "INSERT INTO `$grid` (`type`, `cash`, `name`) VALUES ";
					$insStmts = [];
					for ($i = 0; $i < $nb; $i++) {
						$insStmts[] = "('b', '100.00', ";
					}
					for ($i = 0; $i < $ng; $i++) {
						$insStmts[] = "('g', '100.00', ";
					}
					for ($i = 0; $i < $na; $i++) {
						$insStmts[] = "('a', '100.00', ";
					}
					for ($i = 0; $i < $nc; $i++) {
						$insStmts[] = "('c', '100.00', ";
					}
					for ($i = 0; $i < $nr; $i++) {
						$insStmts[] = "('r', '100.00', ";
					}
					
					shuffle($insStmts);
					
					foreach($insStmts as $i => &$val) {
						$val .= "'bot$i')";
					}
					$q .= implode(",", $insStmts);
					
					if (count($insStmts)) {
						if ($cxn->query($q)) {
							$query = "UPDATE `meta` SET `rnd` = '1' WHERE `meta`.`grid` = '$grid'; ";
							$query2 = "UPDATE `$grid` SET `rnd` = '1';";
							if ($cxn->query($query) && $cxn->query($query2)) {
								$return['status'] = "perfect";
							} else {
								$return['status'] = "error";
								$return['error'] = "update error :".$cxn->error;
							}
						}
						else {
							$return['status'] = "error";
							$return['msg'] = "Bot insertion failed: ". $cxn->error;
							$return['query'] = $q;
						}
					} else {
						$query = "UPDATE `meta` SET `rnd` = '1' WHERE `meta`.`grid` = '$grid'; ";
						$query2 = "UPDATE `$grid` SET `rnd` = '1', `mod_time` = `mod_time`;";
						if ($cxn->query($query) && $cxn->query($query2)) {
							$return['status'] = "perfect";
						} else {
							$return['status'] = "error";
							$return['error'] = "update error :".$cxn->error;
						}
					}
					exit(json_encode($return));
				} else {
					$return['status'] = "wrong";
					$return['msg'] = "game already started";
					exit(json_encode($return));
				}
			} else {
				$return['status'] = "error";
				$return['msg'] = "check rnd query failed: " . $cxn->error;
				exit(json_encode($return));
			}
		} else {
			$return['status'] = "wrong";
			$return['msg'] = "wrong input format";
			exit(json_encode($return));
		}
	}
	exit(json_encode($return));
?>