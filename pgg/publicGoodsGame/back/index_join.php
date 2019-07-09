<?php
	// To diasable direct url access
    if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        // 
        //   Up to you which header to send, some prefer 404 even if 
        //   the files does exist for security
        //
        header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );

        // choose the appropriate page to redirect users 
        die( header( 'location: ../index.php' ) );

    }
	
	else {
		
		require_once("path.php");
		
		$proposedGR = $_POST["gr"];
		$name = $_POST["name"];
		$nrgx = "/^[a-zA-Z0-9-_\x20]{2,32}$/";
		$grrgx = "/^[a-z0-9]{8}$/";
		if ((preg_match($grrgx, $proposedGR) === 1) and (preg_match($nrgx, $name) === 1)) {
            
            $bot_rgx = "/^bot[0-9]*$/";
            if (preg_match($bot_rgx, $name) === 1) {
                $name = "_".$name;
            }
			
			$return = [];
			
			$uuid =  UUID::v4();
			
			$cxn = new mysqli($hostname, $username,  $password, $dbname);
			
			if ($cxn->connect_errno) {
				$return['status'] = "error";
				$return['msg'] = "connection error: " . $cxn->connect_error;
				exit(json_encode($return));
			}
			
			$chkquery = "SELECT EXISTS (SELECT 1 FROM `$dbname`.`meta` WHERE `grid` = '$proposedGR' AND `rnd` = 0) AS 'present';";

			if ($result = $cxn->query($chkquery)) {
				
				$row = $result->fetch_assoc();
				$present = boolval($row['present']);
				if ($present) {
					$addQuery = "INSERT INTO `$dbname`.`$proposedGR` (`type`, `cash`, `uuid`, `name`, `rnd`) VALUES ('x', '100.00', '$uuid', '$name', 0);";
                    $idQuery = "SELECT `idx` FROM `$dbname`.`$proposedGR` WHERE `uuid` = '$uuid';";
					
					if ($cxn->query($addQuery) && ($idResult = $cxn->query($idQuery))) {	
                        $roomid = intval(($idResult->fetch_assoc())['idx']);
                        
						$incQuery = " UPDATE `$dbname`.`meta`  SET `nclients` = `nclients` + 1  WHERE `grid` = '$proposedGR'";
					
						if ($cxn->query($incQuery)) {							
                            
                            
							$return['status'] = "perfect";
							$return['msg'] = "joined $proposedGR";
							$return['GR'] = $proposedGR;
                            $return['roomid'] = $roomid;
							
							session_start();
							$_SESSION['last_file'] = "index_join";
							$_SESSION['grid'] = $proposedGR;
							$_SESSION['host'] = false;	
							$_SESSION['name'] = $name;
							$_SESSION['uuid'] = $uuid;
							$_SESSION['bypass'] = false;
							$_SESSION['rno'] = 0;
                            $_SESSION['roomid'] = $roomid;
                            
							exit(json_encode($return));
						} else {
							$return['status'] = "error";
							$return['msg'] = "inc query failure: ".$cxn->error;
							exit(json_encode($return));
						}
					} else {
						$return['status'] = "error";
						$return['msg'] = "add query failure: ".$cxn->error;
						exit(json_encode($return));
					}
				} else {					
					$return['status'] = "wrong";
					$return['msg'] = "no such game room";
					exit(json_encode($return));
				}
			} else {
				$return['status'] = "error";
				$return['msg'] = "search query failure: " . $cxn->error;
				exit(json_encode($return));
			}
			
			$return['status'] = "perfect";
			$return['msg'] = "joining with GR $proposedGR";
			$return['GR'] = $proposedGR;
			exit(json_encode($return));
		}
	}
	
	function shutdown() {
		if (connection_aborted()){
			$deleteQuery = "DELETE FROM `$proposedGR` WHERE `meta`.`grid` = '$proposedGR'";
			$cxn->query($dropQuery);
			$cxn->query($deleteQuery);
		}
		$cxn->close();
	}
	
	register_shutdown_function('shutdown');
?>