<?php

	$cxn = new mysqli("localhost", "root", "", "pgg_db");
	$grid = "mem_mta1v2zs";
	$query = "SELECT * FROM `$grid`";
    
    if ($result = $cxn->query($query)) {
        while ($row = $result->fetch_assoc()){
            $rnd = $row['rnd'];
            $cash = unserialize(base64_decode($row['cash']));
            foreach ($cash as &$c) {
                $c = intval($c * 1000) / 1000;
            } unset($c);
            $contrib = unserialize(base64_decode($row['contrib']));
            foreach ($contrib as &$co) {
                $co = intval($co * 1000) / 1000;
            } unset($co);
            echo "Contributions at round $rnd: " . http_build_query($contrib, '', ', ') . '<br />';
            echo "Cash after round $rnd: " . http_build_query($cash, '', ', ') . '<br />';
            echo "Balance of round $rnd: " . $row['balance'] . '<br />';
        }
    }
?>