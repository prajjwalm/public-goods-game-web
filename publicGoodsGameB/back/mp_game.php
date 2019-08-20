<?php
	
	// To diasable direct url access
    if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );
        die( header( 'location: ../index.php' ) );
    }
	
	//
	// takes a player's contribution, and calls the bots contributions if all others have already submitted
	//
	
	
	function bound_local ($low, $val, $high) {			// as there is another bound in bots.php
		if ($val < $low) return $low;
		elseif ($val > $high) return $high;
		else return $val;
	}
	session_start();
	
	$_SESSION['last_file'] = "mp_game";
	
	$grid = $_SESSION['grid'];
	$uuid = $_SESSION['uuid'];
	
	$sessOk = preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $uuid);
	$sessOk = $sessOk && preg_match("/^[a-z0-9]{8}$/", $grid);
	
	$return = [];
	
	if (!$sessOk) {
		$return['status'] = "error";
		$return['msg'] = "session currupted";
		exit(json_encode($return));	
	}
		
	require_once("pathU.php");
	
	if (!is_int($_SESSION['rno'])) {
		$return['status'] = "error";
		$return['msg'] = "Incorrect session value for round";
		exit(json_encode($return));
	}
	
    $cxn = new mysqli($hostname, $username,  $password, $dbname);
    
    $maxrndQuery = "SELECT `max_rnd` FROM `meta` WHERE `grid` = '$grid';";
    if (!($maxrndResult = $cxn->query($maxrndQuery))) {
        $return['status'] = "error";
        $return['msg'] = "max_rnd couldn't be retrieved";
        exit(json_encode($return));
    }
    $max_rnd = intval((($maxrndResult->fetch_assoc())['max_rnd']));
    
    if ($_SESSION['rno'] > $max_rnd) {
        $return['status'] = "GAME_OVER";
        exit(json_encode($return));
    } else if ($_SESSION['rno'] == $max_rnd) {
        $return['warn'] = 1;
    }
    
	if (($_SESSION['rno'] % 2 === 1) && is_numeric($_POST['contrib'])) {
		
		$return['type'] = "foo";
		
		if ($cxn->connect_errno) {
			$return['status'] = "error";
			$return['msg'] = "connection error: " . $cxn->connect_error;
			exit(json_encode($return));
		}
		
		$majrndQuery = "SELECT `rnd` FROM `meta` WHERE `grid` = '$grid';"; // updated on payoff and rp response
		$rndQuery = "SELECT `rnd`,`cash` FROM `$grid` WHERE `uuid` = '$uuid';";
		
		if (($rndResult = $cxn->query($rndQuery)) && ($majrndResult = $cxn->query($majrndQuery))) {
			$rndRow = ($rndResult->fetch_assoc());
			$rnd_rec = intval($rndRow['rnd']);
			$cash_rec = floatval($rndRow['cash']);
			
			$majrnd_rec = intval(($majrndResult->fetch_assoc())['rnd']);
			$rndResult->close();
			$majrndResult->close();
			if ($majrnd_rec != $rnd_rec) {
				$return['status'] = "wrong";
				$return['msg'] = " no contribution expected right now";
				exit(json_encode($return));
			}	
		} else {
			$return['status'] = "error";
			$return['msg'] = "round selection error: ".$cxn->error;
			exit (json_encode($return));
		}

		$contrib = number_format(bound_local(0, floatval($_POST['contrib']), $cash_rec), 3, ".", "");
		
		
		// update player's round	
		$nextRnd = $rnd_rec + 1;
		$updateQuery = "UPDATE `$grid` SET `contrib`= $contrib, `rnd` = $nextRnd WHERE `uuid` = '$uuid';";
		
		if (! $cxn->query($updateQuery)) {
			$return['status'] = "error";
			$return['msg'] = "update Query failure: ". $cxn->error;
			exit(json_encode($return));
		}
		$_SESSION['rno'] += 1;
		
		
		// finally check if all other inputs have been taken
		$Xquery = "SELECT `idx`, `rnd` FROM `$grid` WHERE `uuid` IS NOT NULL";
		$complete = true;
		if ($resultX = $cxn->query($Xquery)) {		
			while ($rowX = $resultX->fetch_assoc()) {
				//
				// if all rows are equal to $_SESSION['rno'], retrieve bots, call foo return cash array
				// else return all the new cases
				//
				$complete = $complete && ($rowX['rnd'] == $nextRnd);
			}
			$resultX->close();
			$return['all_done'] = $complete;
			if ($complete) {
				
				$_SESSION['times']['contrib_list'] = time();
				
				// all inputs taken update the bots' rounds
				$uq1 = "UPDATE `$grid` SET `rnd` = $nextRnd WHERE `type` != 'x';"; // remember to increase security
				
				if (! $cxn->query($uq1)) {
					$return['status'] = "error";
					$return['msg'] = "couldn't update bot rounds: ".$cxn->error;
					exit(json_encode($return));
				}
				
				require_once "./bots.php";
				$return['hahaha'] = "hohoho";
				if ($_SESSION['rno'] == 2) {						// wrt caller the 1st round got over, i.e. game just started
					$activeSoc = new Society($grid, 0, 100);
					$return['foo'] = $activeSoc->foo();
					
				} else {			// payoff
					$activeSoc = new Society($grid, 1, 100);
					$return['foo'] = $activeSoc->foo();
				}
				$return['rnd'] = $nextRnd;
                $return['status'] = "perfect";
				exit(json_encode($return));
			}
		
		} else {
			$return['status'] = "error";
			$return['msg'] = "select query failed: ".$cxn->error;
			exit(json_encode($return));
		}
		
		// If all entries are updated call entries for all bots
		exit(json_encode($return));
		
	} elseif ($_SESSION['rno'] % 2 === 0) {
		
		$return['type'] = "bar";
		
		if ($cxn->connect_errno) {
			$return['status'] = "error";
			$return['msg'] = "connection error: " . $cxn->connect_error;
			exit(json_encode($return));
		}
		
		// check if the round is right
		$majrndQuery = "SELECT `rnd` FROM `meta` WHERE `grid` = '$grid';"; // updated on payoff and rp response
		$rndQuery = "SELECT `rnd`,`cash` FROM `$grid` WHERE `uuid` = '$uuid';";
		
		if (($rndResult = $cxn->query($rndQuery)) && ($majrndResult = $cxn->query($majrndQuery))) {
			$rndRow = ($rndResult->fetch_assoc());
			$rnd_rec = intval($rndRow['rnd']);
			$cash_rec = floatval($rndRow['cash']);
			
			$majrnd_rec = intval(($majrndResult->fetch_assoc())['rnd']);
			$rndResult->close();
			$majrndResult->close();
			if ($majrnd_rec != $rnd_rec) {
				$return['status'] = "wrong";
				$return['msg'] = " no rp expected right now";
				exit(json_encode($return));
			}	
		} else {
			$return['status'] = "error";
			$return['msg'] = "round selection error: ".$cxn->error;
			exit (json_encode($return));
		}
		
		// sanitize the rp input
		$inputOk = true;
		
		$rp = [];
		$total_spent = 0;
		$epsilon = 0.001;			// for float inaccruacy
		
		$jdrp = json_decode($_POST['rp']);
		foreach ($jdrp as $idx => $val) {
		
			// $idx most likely string, $val should be a float
			if (!is_numeric($val) || !is_numeric($idx)) {		// || 0 for null, just in case
				$inputOk = false;
				break;
			}
			
			if ($total_spent + 2 * $epsilon >= $cash_rec) break;
			
			$fval = floatval($val);
			$spent = ($fval >= 0) ? $fval * 1.2 : $fval * (-0.2);
			
			if ($total_spent + $epsilon + $spent >= $cash_rec) {
				$spent = $cash_rec - $total_spent;
				$fval = ($fval >= 0) ? $spent / 1.2 : $spent * (-5);
				$total_spent = $cash_rec;
				break;
			} else {
				$total_spent += $spent;
			}
			
			$rp[intval($idx)] = $fval;
			
		} unset($idx, $val);
		
		$return['pcontrib'] = $_POST['contrib'];
		$return['rno'] = $_SESSION['rno'];
		$return['prp'] = $rp;
		
		if (!$inputOk) {
			$return['status'] = "wrong";
			$return['msg'] = "invalid input";
			exit(json_encode($return));			
		}
		
		$storage = base64_encode(serialize($rp));
		
		// exit(json_encode($return));
		
		// update player's round	
		$nextRnd = $rnd_rec + 1;
		$updateQuery = "UPDATE `$grid` SET `rp`= '$storage', `rnd` = $nextRnd WHERE `uuid` = '$uuid';";
		
		
		if (! $cxn->query($updateQuery)) {
			$return['status'] = "error";
			$return['msg'] = "update Query failure: ". $cxn->error;
			exit(json_encode($return));
		}
		$_SESSION['rno'] += 1;
		
		
		// finally check if all other inputs have been taken
		$Xquery = "SELECT `idx`, `rnd` FROM `$grid` WHERE `uuid` IS NOT NULL";
		$complete = true;
		if ($resultX = $cxn->query($Xquery)) {		
			while ($rowX = $resultX->fetch_assoc()) {
				//
				// if all rows are equal to $_SESSION['rno'], retrieve bots, call foo return cash array
				// else return all the new cases
				//
				$complete = $complete && ($rowX['rnd'] == $nextRnd);
			}
			$resultX->close();
			$return['all_done'] = $complete;
			if ($complete) {
				
				$_SESSION['times']['rp_list'] = time();
				
				// all inputs taken update the bots' rounds
				$uq1 = "UPDATE `$grid` SET `rnd` = $nextRnd WHERE `type` != 'x';"; // remember to increase security
				
				if (! $cxn->query($uq1)) {
					$return['status'] = "error";
					$return['msg'] = "couldn't update bot rounds: ".$cxn->error;
					exit(json_encode($return));
				}
				
				require_once "./bots.php";
				$return['hahaha'] = "hehehe";
		
				$activeSoc = new Society($grid, 2, 100);
				$return['bar'] = $activeSoc->bar();
				$return['rnd'] = $nextRnd;
				exit(json_encode($return));
			}
		
		} else {
			$return['status'] = "error";
			$return['msg'] = "select query failed: ".$cxn->error;
			exit(json_encode($return));
		}
		
		exit(json_encode($return));
		
	} else {
		$return['status'] = "wrong";
		$return['msg'] = "invalid input";
		$return['rno'] = $_SESSION['rno'];
		$return['pcontrib'] = $_POST['contrib'];
		$return['prp'] = $_POST['rp'];
		exit(json_encode($return));
	}
?>