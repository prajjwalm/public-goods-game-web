<?php
	// To diasable direct url access
    if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );
        die( header( 'location: ../index.php' ) );
    }
	
	session_start();

	require_once("path.php");
	
	$grid = $_SESSION['grid'];
	$uuid = $_SESSION['uuid'];

	$sessOk = (preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $uuid)===1);
	$sessOk = $sessOk && (preg_match("/^[a-z0-9]{8}$/", $grid)===1);
	
	$return = [];
	
	if ($sessOk) {
		$cxn = new mysqli($hostname, $username,  $password, $dbname);
	
		if ($cxn->connect_errno) {
			$return['status'] = "error";
			$return['msg'] = "connection error: " . $cxn->connect_error;
			exit(json_encode($return));
		}
		
		// update ping 
		$uQ = "UPDATE `$grid` SET `mod_time`=`mod_time`, `ping` = NOW() WHERE `uuid` = '$uuid';";			
		if (!$cxn->query($uQ)){
			$return['status'] = "error";
			$return['msg'] = $cxn->error;
			exit(json_encode($return));
		}
		
		// check if change reqd			
		$time = time() - 30;			// if some player is not updated for thirty seconds, destroy him
		
		$cleanQ = "DELETE FROM `$grid` WHERE UNIX_TIMESTAMP(`ping`) < $time AND `type` = 'x'";
		$cxn->query($cleanQ);
		
		if (!($cnt = $cxn->query("SELECT ROW_COUNT();"))) {	
			$return['status'] = "error";
			$return['msg'] = $cxn->error;
			exit(json_encode($return));
		}
		$return['alt_rows'] = intval(($cnt->fetch_assoc())['ROW_COUNT()']);
		
		if ($return['alt_rows']) {
			// table changed, update meta
			$umQ = "UPDATE `meta` SET `nclients` = `nclients` - 1 WHERE `grid` = '$grid';";
			if (!$cxn->query($umQ)){
				$return['status'] = "error";
				$return['msg'] = "update meta on ping failed: ".$cxn->error;
				exit(json_encode($return));
			}
		} else {
			// I didn't change the table, so I now check if anyone else did
			// for this I check the number of people in the game vs the number I remember
			// i.e. length ($grid) vs $_SESSION['nplayers']
			if (array_key_exists('nplayers', $_SESSION)) {
				$nrowsQ = "SELECT COUNT(*) FROM `$grid`;";
				if ($result = $cxn->query($nrowsQ)) {
					$row = $result->fetch_assoc();
				
					$return['alt_rows'] = $_SESSION['nplayers'] - intval($row['COUNT(*)']);
					$_SESSION['nplayers'] = $row['COUNT(*)'];

				} else {
					$return['status'] = "error";
					$return['msg'] = "count error: ". $cxn->error;
					exit(json_encode($return));
				}
			}
		}
		
		$cnt->free();
		$return['status'] = "perfect";
		exit(json_encode($return));

	} else {
		$return['status'] = "error";
		$return['msg'] = "session ruined";
		exit(json_encode($return));
	}
?>