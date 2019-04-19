<?php
	// To diasable direct url access
    // if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        // header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );
        // die( header( 'location: ../index.php' ) );
    // }
	
	require_once("path.php");

	//
	// Create the json file with data on the bots
	//
	// Note this script will be run by me personally, and cannot be 
	// accessed from the website, therefore it seems ok to write in 
	// my home directory
	//
	if ($overwrite) {
		$fp = fopen($file_path, 'w');
		$wstatus = fwrite($fp, json_encode($bots, JSON_PRETTY_PRINT));
		if ($wstatus === false) {
			die("Write failed");
		} else {
			echo "Write Successful<br />";
		}
		fclose($fp);
	} else {
		if ($fp = fopen($file_path, 'x')) {
			$wstatus = fwrite($fp, json_encode($bots, JSON_PRETTY_PRINT));
			if ($wstatus === false) {
				die("Write failed");
			} else {
				echo "Write Successful<br />";
			}
			fclose($fp);
		}
	}
	
	//
	// Create the database, abort if 
	// a. it already existed
	// b. some error occured while creating it 
	//
	$cxn = new mysqli($hostname, $username, $password);
	
	if ($cxn->connect_errno) {
		die("connection failed: " . $cxn->connect_error);
	}
	
	$query = "CREATE DATABASE IF NOT EXISTS $dbname";
	
	if ($cxn->query($query) === true) {
		// 
		// database created successfully, 
		// now to create a table which will hold all game room ids, number of clients playing and their host ip address
		// 
		$query2 = "CREATE TABLE IF NOT EXISTS `$dbname`.`meta` ( `grid` VARCHAR(8) NOT NULL , `nclients` TINYINT UNSIGNED NOT NULL DEFAULT '1' , 
		`host` VARCHAR(255) NOT NULL , `rnd` SMALLINT NOT NULL DEFAULT 0, `last_balance` DECIMAL(21,3) NOT NULL DEFAULT 0, `rp` BLOB NULL DEFAULT NULL,
		`mod_time` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`grid`), UNIQUE (`host`)) ENGINE = InnoDB;";
		
		if ($cxn->query($query2) === true) {
			echo "Operation successful";
		} else {
			die("Error while creating table: " . $cxn->error);
		}
		
	} else {
		die("Error while creating database: " . $cxn->error);
	}

	$cxn->close();

?>