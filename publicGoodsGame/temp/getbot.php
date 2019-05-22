<?php

	require ("../back/bots.php");

	$cxn = new mysqli("localhost", "root", "", "pgg_db");
	
	$grid = "jcntkl43";
	$idx = 8;
	
	
	$query = "SELECT `botobj` FROM `$grid` WHERE `idx` = $idx;";
	
	$s = unserialize(base64_decode((($cxn->query($query))->fetch_assoc())['botobj']));
	
	echo get_class($s).'<hr/>';
	
	$s->getProps();

?>