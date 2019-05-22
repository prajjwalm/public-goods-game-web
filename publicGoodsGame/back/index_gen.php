<?php
	// To diasable direct url access
    if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        // 
        //   Up to you which header to send, some prefer 404(alternate: 403) even if 
        //   the files does exist for security
        //
        header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );

        // choose the appropriate page to redirect users 
        die( header( 'location: ../index.php' ) );

    }
	
	else {
		// contains most definations
		require_once("path.php");
		
		// sanitation
		$hname = $_POST["name"];
		$rgx = "/^[a-zA-Z0-9-_\x20]{2,32}$/";
		if (preg_match($rgx, $hname) === 1) {
			
			// just a useful function
			function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyz') {
				$pieces = [];
				$max = strlen($keyspace) - 1;
				for ($i = 0; $i < $length; ++$i) {
					$pieces []= $keyspace[random_int(0, $max)];
				}
				return implode('', $pieces);
			}
			
			
			$uuid =  UUID::v4();
			$return = [];
			
			$cxn = new mysqli($hostname, $username, $password, $dbname);
			
			if ($cxn->connect_errno) {
				$return['status'] = "error";
				$return['msg'] = "connection error: " . $cxn->connect_error;
				exit(json_encode($return));
			}
			
			
			do {
				
				$proposedGR = random_str(8);
				$chkquery = "SELECT EXISTS (SELECT 1 FROM `$dbname`.`meta` WHERE `grid` = '$proposedGR') AS 'present';";
				
				if ($result = $cxn->query($chkquery)) {
					$row = $result->fetch_assoc();
					$present = boolval($row['present']);
				} else {
					$return['status'] = "error";
					$return['msg'] = "search query failure: " . $cxn->error;
					exit(json_encode($return));
				}
				
				$result->free();
				
			} while ($present /* existing game room found*/);
			
			$initquery = "INSERT INTO `$dbname`.`meta` (`grid`, `host`) VALUES ('$proposedGR', '$uuid')";
			
			if ($cxn->query($initquery)) {
				// game room created successfully now send this guy there as the host
				// i.e. this guy controls the start, abort buttons on the game room page
				// also this guy adjusts the bot populations as he deems fit (before the game starts)
				// (the opinion columns will be added then)
				$createquery = "CREATE TABLE `$dbname`.`$proposedGR` ( `type` CHAR(1) NOT NULL , `cash` DECIMAL(21,3) NOT NULL ,
				`contrib` DECIMAL(21,3) NOT NULL DEFAULT '0' , `uuid` VARCHAR(255) NULL DEFAULT NULL , `name` VARCHAR(32) NULL DEFAULT NULL,
				`host` BOOLEAN NOT NULL DEFAULT FALSE , `mod_time` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`rp` BLOB NULL DEFAULT NULL, `idx` INT NOT NULL AUTO_INCREMENT, `rnd` SMALLINT NOT NULL DEFAULT 0, `botobj` BLOB NULL DEFAULT NULL,
				`ping` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`update_reqd` BOOLEAN NOT NULL DEFAULT FALSE, PRIMARY KEY (`idx`), UNIQUE (`name`), UNIQUE (`uuid`)) ENGINE = InnoDB; ";
				// $createquery = "CREATE TABLE `$dbname`.`$proposedGR` ( `type` CHAR(1) NOT NULL , `cash` DECIMAL(21,3) NOT NULL ,
				// `contrib` DECIMAL(21,3) NOT NULL DEFAULT '0' , `uuid` VARCHAR(255) NULL DEFAULT NULL , `name` VARCHAR(32) NULL DEFAULT NULL,
				// `host` BOOLEAN NOT NULL DEFAULT FALSE , `hf` FLOAT NULL DEFAULT NULL , `vol` FLOAT NULL DEFAULT NULL , `mod_time` TIMESTAMP
				// on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `op` VARCHAR(255) NULL DEFAULT NULL, `rp` VARCHAR(255)
				// NULL DEFAULT NULL, `idx` INT NOT NULL AUTO_INCREMENT, `rnd` DECIMAL(7,1) NULL DEFAULT NULL,
				// PRIMARY KEY (`idx`), UNIQUE (`name`), UNIQUE (`uuid`)) ENGINE = InnoDB; ";
				
				if ($cxn->query($createquery)) {
					//
					// Notice: the host is guaranteed to be the first player of the room
					// then shall come all the other human players
					//
					$addQuery = "INSERT INTO `$dbname`.`$proposedGR` (`type`, `cash`, `uuid`, `name`, `host`, `rnd`) VALUES ('x', '100.00', '$uuid', '$hname', TRUE, 0)";
					
					if ($cxn->query($addQuery)) {						
						$return['status'] = "perfect";
						$return['msg'] = "new game room with GR $proposedGR created";
						$return['GR'] = $proposedGR;
						
						//
						// now that the new game room is initialized, clean up any unused game rooms, this doesn't relate to the 
						// currently requested game in any manner, but seems like the right time to do this
						//
						$xtime = time() - 3600; 	// inactive since 1 hr
						
						$xquery = "SELECT `grid` FROM `meta` WHERE UNIX_TIMESTAMP(`mod_time`) < $xtime";
						if ($xresult = $cxn->query($xquery)) {
							while ($xrow = $xresult->fetch_assoc()){
								$xgrid = $xrow['grid'];
								$cxn->query("DROP TABLE IF EXISTS $xgrid");
							}
						}
						$cxn->query("DELETE FROM `meta` WHERE UNIX_TIMESTAMP(`mod_time`) < $xtime");
						
						session_start();
						$_SESSION['last_file'] = "index_gen";
						$_SESSION['grid'] = $proposedGR;
						$_SESSION['host'] = true;	
						$_SESSION['name'] = $hname;
						$_SESSION['uuid'] = $uuid;
						$_SESSION['bypass'] = false;
						$_SESSION['rno'] = 0;
						
						exit(json_encode($return));
					} else {
						$return['status'] = "error";
						$return['msg'] = "add query failure: ".$cxn->error;
						exit(json_encode($return));
					}
				} else {
					$return['status'] = "error";
					$return['msg'] = "create query failure: ".$cxn->error;
					exit(json_encode($return));
				}				
			} else {
				$return['status'] = "error";
				$return['msg'] = "insert query failure: ". $cxn->error;
				exit(json_encode($return));
			}
			
			
		}
		else {
			exit(json_encode("sanity check failed"));
		}
	}
	
	function shutdown() {
		if (connection_aborted()){
			//
			// remove the table that was just created and remove its row
			// from the meta table, also note that the session variables 
			// are cleared at this point
			//
			$dropQuery = "DROP TABLE `$proposedGR`";
			$deleteQuery = "DELETE FROM `meta` WHERE `meta`.`grid` = '$proposedGR'";
			$cxn->query($dropQuery);
			$cxn->query($deleteQuery);
		}
		$cxn->close();
	}
	
	register_shutdown_function('shutdown');
	
?>