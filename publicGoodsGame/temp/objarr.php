<?php


	class a {
		private $idx;
		public $k;
		
		public function __construct ($idx) {
			$this->k = mt_rand();
			$this->idx = $idx;
		}
		
		public function getData ($cls) {
			echo "My rand val is $this->k<br />";
			foreach ($cls->A as $idx => $a) {
				printf( "me: %d, val: %d<br />", $a === $this, $a->k);
			}
		}
	}
	
	class enc {
		public $A;
		
		public function __construct() {	
			$this->A = [];
			for ($i = 0; $i < 5; $i++) {
				$this->A[] = new a($i);
			}
		}
		
		public function foo() {
			for ($i = 0; $i < 5 ; $i++) {
				$this->A[$i]->getData($this);
			}
		}
	}
	
	$e = new enc();
	$e->foo();
	
?>