<?php 
	// To diasable direct url access
    if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );
        die( header( 'location: ../index.php' ) );
    }
	
	session_start();
	$_SESSION['last_file'] = "mp_players";

	require_once("path.php");

	$grid = $_SESSION['grid'];
	$uuid = $_SESSION['uuid'];
	$host = $_SESSION['host'];

	if (isset($_SESSION['times']['player_list'])) {
		$time = intval($_SESSION['times']['player_list']);			// $_SESSION['times'] stores all unix timestamps
	} else {
		$time = 0;
	}
	
	$return = $_SESSION;
	
	$inputOk = preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $uuid);
	$inputOk = $inputOk && preg_match("/^[a-z0-9]{8}$/", $grid);
	
	if ($inputOk) {
		$cxn = new mysqli($hostname, $username,  $password, $dbname);
		
		if ($cxn->connect_errno) {
			$return['status'] = "error";
			$return['msg'] = "connection error: " . $cxn->connect_error;
			exit(json_encode($return));
		}
		
		session_write_close();
		ignore_user_abort(false);
		set_time_limit(0); 		// ignores the duration of sleep
		
		$i = 0;
		
		if ($host){
			$query = "SELECT `name`,`idx` FROM `$grid` WHERE `uuid` != '$uuid' AND UNIX_TIMESTAMP(`mod_time`) > $time";
		} else {
			$query = "SELECT `name`,`host`,`idx` FROM `$grid` WHERE `uuid` IS NOT NULL AND UNIX_TIMESTAMP(`mod_time`) > $time";
		}
		
		$mrndQuery = "SELECT `rnd` FROM `meta` WHERE `grid` = '$grid'";
		$lenQuery = "SELECT `idx`, `type`, `cash`, `name` FROM `$grid`";
		
		while ($i < 60) {
			$resultB = $cxn->query($mrndQuery);
			$major_rnd = intval(($resultB->fetch_assoc())["rnd"]);
			$resultB->close();
			
			$resultA = $cxn->query($query);			
			$resultArr = $resultA->fetch_all(MYSQLI_ASSOC);
			$resultA->close();
						
			if ($major_rnd > 0) {
				$players = [];
				if ($resultLen = $cxn->query($lenQuery)) {
					$nplayers = $resultLen->num_rows;
					while ($rowL = $resultLen->fetch_assoc()) {
						$players[$rowL["idx"]] = [ 
							"type" => $rowL["type"],
							"cash" => $rowL["cash"],
							"name" => $rowL["name"],
						];
					}
					$resultLen->close();
				} else {
					$return['status'] = "error";
					$return['msg'] = "connection error: ".$cxn->error;
					exit(json_encode($return));
				}
				break;
			}
			if(!empty($resultArr)) break;				
					
			sleep(1);
			$i++;
		}
		
		session_start();
		$_SESSION['times']['player_list'] = time();
		
		if ($major_rnd > 0) {
			$rndQuery = "SELECT `rnd` FROM `$grid` WHERE `uuid` = '$uuid'";
			$resultRnd = $cxn->query($rndQuery);
			$self_rnd = intval(($resultRnd->fetch_assoc())['rnd']);
			$resultRnd->close();
			
			$_SESSION['rno'] = $self_rnd;
			$_SESSION['nplayers'] = intval($nplayers);
			$return = [
				'arr' => $resultArr,
				'mrnd' => $major_rnd,
				'srnd' => $self_rnd,
				'players' => $players,
			];
			exit(json_encode($return));
		} else {
			$return = [
				'arr' => $resultArr,
				'bool' => $major_rnd,
			];
			exit(json_encode($return));
		}
	}
	exit(json_encode($return));
?>