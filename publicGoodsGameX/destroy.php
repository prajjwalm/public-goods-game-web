<?php

	// To diasable direct url access
    // if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        // header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );
        // die( header( 'location: index.php' ) );
    // }

	session_start();
	session_unset();
	session_destroy();
	
	header("Location: index.php");
	exit(0);
?>