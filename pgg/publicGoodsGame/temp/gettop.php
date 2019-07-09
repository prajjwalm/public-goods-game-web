<?php

	function cmp ($x, $y) {
		return -($x->cash <=> $y->cash);
	}

	function getTop($arr, $n) {
		if (count($arr) < $n) {
			return false;
		}
		else {
			uasort($arr, 'cmp');
			return array_slice(array_keys($arr), 0, $n);
		}
	}
	
	class bot {
		public $cash;
		
		public function __construct () {
			$this->cash = mt_rand() % 1000;
		}
	}
	
	$Bots = [];
	
	for ($i = 0; $i < 10; $i++) {
		$Bots[] = new bot();
		$cash = $Bots[$i]->cash;
		echo "$i bot has cash: $cash <br/>";
	}
	
	
	
	$lbd = getTop($Bots, 3);
	foreach ($lbd as $i => $v) {
		echo $i;echo$v."<br/>";
	}
?>