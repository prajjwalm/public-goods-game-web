<?php
	// To diasable direct url access
    if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );
        die( header( 'location: ../index.php' ) );
    }
	
	//
	// polls over rp
	//
	
	session_start();

	require_once("path.php");

	// storing session and sanity check
	$grid = $_SESSION['grid'];
	$uuid = $_SESSION['uuid'];
	$name = $_SESSION['name'];
	$rnd_caller = $_SESSION['rno'];

	$return = [];
	
	if (isset($_SESSION['times']['contrib_list'])) {
		if (isset($_SESSION['times']['rp_list'])) {
			$time = max(intval($_SESSION['times']['rp_list']), intval($_SESSION['times']['contrib_list']));
		} else {
			$time = intval($_SESSION['times']['contrib_list']);
		}
	} else {
		if (isset($_SESSION['times']['rp_list'])) {
			$time = intval($_SESSION['times']['rp_list']);
		} else {
			if (isset ($_SESSION['times']['player_list'])) {
				$time = intval($_SESSION['times']['player_list']);
			} else {
				$return['status'] = "warning";
				$return['msg'] = "danger: time not accessable";
				$time = time() - 2;
			}			
		}
	}

	
	
	$sessOk = preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $uuid);
	$sessOk = $sessOk && preg_match("/^[a-z0-9]{8}$/", $grid);
	$sessOk = $sessOk && is_int($rnd_caller);
	
	if (!$sessOk) {
		$return['status'] = "error";
		$return['msg'] = "session variables may be corrupted";
		exit(json_encode($return));
	}

	$cxn = new mysqli($hostname, $username,  $password, $dbname);
	
	if ($cxn->connect_errno) {
		$return['status'] = "error";
		$return['msg'] = "connection error: " . $cxn->connect_error;
		exit(json_encode($return));
	}
	
	//
	// verify if the player's round is just one more than the soc's
	// i.e. if the player has completed just one step more
	//
	$rndQuery = "SELECT `rnd` FROM `meta` WHERE `grid` = '$grid'";
	if ($rndResult = $cxn->query($rndQuery)) {
		$rnd_major = intval(($rndResult->fetch_assoc())['rnd']);
		//
		// rnd_major is updated when rp_exec occurs, rnd_caller is updated when rp is given (mp_game.php)
		// so rnd_caller should be typically exactly 1 more then rnd_major in the correct case.
		// even browser refresh won't destroy the session, though closing the tab will, which is ok
		//
		if ((($rnd_caller != $rnd_major + 1) || ($rnd_major % 2 != 0) || $rnd_major <= 0) && (!$_SESSION['bypass'])) {
			//
			// it might be that it is the session that is wrong, but the db is ok with polling,
			//
			$srndQuery = "SELECT `rnd` FROM `$grid` WHERE `uuid` = '$uuid'";
			if ($srndResult = $cxn->query($srndQuery)) {
				$srnd = intval(($srndResult->fetch_assoc())['rnd']);
				$srndResult->close();
				if (($rnd_major % 2 == 0) && ($srnd == $rnd_major + 1) && ($rnd_major > 0)) {
					$_SESSION['rnd'] = $srnd;
					$rnd_caller = $srnd;
				}
				else {
					$return['status'] = "nosync";
					$return['msg'] = "incorrect stage";
					$return['rc'] = $rnd_caller;
					$return['rm'] = $rnd_major;
					exit(json_encode($return));					
				}
			} else {
				$return['status'] = "error";
				$return['msg'] = "couldn't fetch self round for verification: ". $cxn->error;
				exit(json_encode($return));
			}
		}
	} else {
		$return['status'] = "error";
		$return['msg'] = "couldn't fetch major round for verification: ". $cxn->error;
		exit(json_encode($return));
	}
	
	
	// begin polling to check for new contributions
	$_SESSION['bypass'] = false;
	session_write_close();
	ignore_user_abort(false);
	set_time_limit(0);
	
	$i = 0;
	$query = "SELECT `idx`, `name`, `rnd`, UNIX_TIMESTAMP(`mod_time`) AS `t` FROM `$grid` WHERE `uuid` IS NOT NULL";
	$return['update'] = 0;			
	//
	// 0 => no update, expect nothing further; continue polling 
	// 1 => new result but not yet complete, expect new contributors name, idx; continue polling 
	// 2 => complete, expect rp array; end polling
	//
	
	$new_cases = [];
	while ($i < 60) {
		
		$complete = true;
		// heavy zone: optimization important
		if ($result = $cxn->query($query)) {		
			while ($row = $result->fetch_assoc()) {
				//
				// if all rows are equal to rnd_caller, retrieve bots, call foo return cash array
				// else return all the new cases
				//
				if ($time < intval($row['t'])) {
					$new_cases[] = [
						"idx"  => $row['idx'],
						"name" => $row['name'],
						"time" => intval($row['t']),
					];
				}
				$complete = $complete && ($row['rnd'] == $rnd_caller);
			}
			$result->close();
			if ($complete) {
				$return['update'] = 2;
				break;
			} elseif (! empty($new_cases)) {
				$return['update'] = 1;
				break;
			}
		} else {
			$return['status'] = "error";
			$return['msg'] = "select query failed: ".$cxn->error;
			exit(json_encode($return));
		}
		
		sleep(2);			// do not reduce ! (for the sake of the code that follows)
		$i++;
	}
	
	
	session_start();
	$_SESSION['times']['rp_list'] = time();
	$return['stime'] = $_SESSION['times']['rp_list'];
	// $return['ctime'] = $_SESSION['times']['contrib_list'];
	// $return['ptime'] = $_SESSION['times']['player_list'];
	$return['chktime'] = $time;
	
	$return['new_cases'] = $new_cases;
	if ($return['update'] === 2) {
		// all human contributions taken, the last caller should have called bots.php
		$botQuery = "SELECT EXISTS (SELECT * FROM `$grid` WHERE `type` != 'x' AND `rnd` != $rnd_caller) AS 'present'";
		if (!($botResult = $cxn->query($botQuery))) {
			$return['status'] = "error";
			$return['msg'] = "couldn't verify bot status";
			exit(json_encode($return));
		}
		
		// present => stale bot values present?
		$present = boolval(($botResult->fetch_assoc())['present']);
		$botResult->free();
		
		if (! $present) {
			//
			// it may be so that the new bot values are currently being calculated, or updated
			// for safety's sake give the server atleast 1 second time to finish computation before any
			// client requests the new values
			//
			sleep(1);
			
			
			$q = "SELECT `rp` FROM `meta` WHERE `grid` = '$grid'";
			
			if ($r = $cxn->query($q)){
				$return['bar'] = unserialize(base64_decode(($r->fetch_assoc())['rp']));
				$r->close();
				$return['status'] = "perfect rp";
				$return['rnd'] = $rnd_caller;
				exit(json_encode($return));
			} else {
				$return['status'] = "error";
				$return['msg'] = "couldn't retrieve computed values: ".$cxn->error;
				exit(json_encode($return));
			}
		}
		else {
			//
			// everyone is done but no bots have been computed
			//
				// taken directly from mp_game.php
				// all inputs taken update the bots' rounds
			$uq1 = "UPDATE `$grid` SET `rnd` = `rnd` + 1 WHERE `type` != 'x';"; // remember to increase security
			
			if (! $cxn->query($uq1)) {
				$return['status'] = "error";
				$return['msg'] = "couldn't update bot rounds: ".$cxn->error;
				exit(json_encode($return));
			}
			
			require_once "./bots.php";
			$activeSoc = new Society($grid, 2, 100);
			$activeSoc->bar();
			// forcibly poll again
			$return['update'] = 1;
			$_SESSION['bypass'] = true;
			$return['rnd'] = $rnd_caller;
			exit(json_encode($return));	
		}
	} elseif ($return['update'] === 1) {
		//
		// further polling
		//
		exit(json_encode($return));
	} else {
		//
		// further polling
		//
		exit(json_encode($return));
	}

	exit(json_encode($return));

	
?>