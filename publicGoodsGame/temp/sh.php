<?php
	
	function shuffle_assoc(&$array) {
        $keys = array_keys($array);

        shuffle($keys);

        foreach($keys as $key) {
            $new[$key] = $array[$key];
        }

        $array = $new;

        return true;
    }
	
	$arr = [];
	
	$arr[1] = 'a';
	$arr[2] = 'b';
	$arr[3] = 'c';
	
	foreach($arr as $i=>$v) {
		echo $v;
	} unset($i, $v);
	
	shuffle_assoc($arr);
	
	foreach($arr as $i=>$v) {
		echo $v;
	} unset($i, $v);
	
	echo $arr[3];
	echo $arr[2];
	echo $arr[1];
	
	
?>