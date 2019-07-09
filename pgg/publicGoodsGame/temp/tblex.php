<?php
	$cxn = new mysqli("localhost", "root", "", "pgg_db");
	
	if ($cxn->query("SELECT 1 FROM `abcdefgh`")) {
		echo 0;
	} elseif (preg_match("/^Table .* doesn't exist$/",$cxn->error) === 1){
		echo 3;
	} else {
		echo 2;
	}
	
?>