<?php
	
	//
	// Note: the file name 'bots.php' is slighlty misleading, this file is the core of the society and handles 
	// eveything once the inputs are taken
	// 
	
	
	// To diasable direct url access
    if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );
        die( header( 'location: ../index.php' ) );
    }
	
	require_once("path.php");


	//
	// To a bot, another player will simply be idx, entries of two arrays: cash and contrib
	//
	interface Bot {
		//
		// members
		// hf -> frac(float in [0,1])/NULL, vol -> frac/NULL,
		// cash -> decimal, contrib -> decimal, payoff -> decimal,
		// op -> array{int: frac}, $rp -> array{int: decimal}
		//
		public function call ($soc);				// return decimal
		public function request_rp ($soc);			// return array
	
		public function payoff_listener ($soc, $payoff);		// return void
		public function rp_listener ($soc, $rp);						// return void
		
	}

	/* Constants */
	$rp_factor = 0.2;

	/* Support Functions */
	function randf ($min,$max) {
		return ($min + lcg_value() * ($max - $min));
	}

	function cmp ($x, $y) {
		return -($x <=> $y);
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

	function bound ($low, $val, $high) {
		if ($val < $low) return $low;
		elseif ($val > $high) return $high;
		else return $val;
	}
	
	function shuffle_assoc($array) {
        $keys = array_keys($array);

        shuffle($keys);

        foreach($keys as $key) {
            $new[$key] = $array[$key];
        }

        return $new;

    }

	/* Bot Constants (const for a room for all bots) */
	$g = [
		"icop" 		=> 0.5,
		"irpop" 	=> 0.5,
		"minselfc" 	=> 0.3,
		"coolrnds" 	=> 4,				// int
		"p0"		=> 0.2,
		"r0"		=> 0.2,
		"max_rploss" => 0.4,
		"min_vol"	=> 0.2,
		"max_vol" 	=> 0.5,
		"opvol_ratio" => 2,
		"m"			=> 0.5,				// for rp
		"vol_rp_const" => 2,
	];

	$b = [
		"icop" 		=> 0.5,
		"irpop" 	=> 0.5,
		"p0" 		=> 0.4,
		"r0"		=> 0.2,
		"min_vol"	=> 0.2,
		"max_vol" 	=> 0.5,
		"max_rploss" => 0.4,
		"opvol_ratio" => 5,
		"m1" 		=> 4,
		"m2" 		=> 3,
		"hf_loss_eq" => 0.8,
		"vol_rp_const" => 0.8,
	];
	$a = [];
	$c = [];
	$r = [];

	$args = [
		"g" => $g,
		"b" => $b,
		"a" => $a,
		"c" => $c,
		"r" => $r,
	];

	/* Bot Implementations */
	class Good implements Bot {
		
		public $cash;
		public $contrib;
		
		private $idx;
		private $hf;
		private $vol;
		
		private $cop;
		private $rpop;
		
		private $vcop;
		private $vrpop;
		
		private $cooldown;
		
		private $msg;
		
		
		public function __construct ($soc, $idx) {
			
			global $args;
			
			$this->cash = $soc->icash;
			$this->contrib = 0;
			
			$this->idx = $idx;
			
			$this->hf = randf(0.8, 1);
			$this->vol = randf($args['g']['min_vol'] * 2, $args['g']['max_vol'] / 2);
			
			// $theta = randf(pi() / 6, pi() / 4);
			// $this->vcop = $args['g']['opvol_ratio'] * sin($theta);
			// $this->vrpop = $args['g']['opvol_ratio'] * cos($theta);
			
			$this->vcop = randf(1,3);
			$this->vrpop = randf(1,3);
			
			$this->cop = [];
			$this->rpop = [];
			$this->cooldown = [];
			
			foreach ($soc->Cash as $botidx => $botcash) {
				if ($botidx != $this->idx) {
					$this->cop[$botidx] = $args['g']['icop'];
					$this->rpop[$botidx] = $args['g']['irpop'];
					$this->cooldown[$botidx] = 0;
				}
			} unset($botidx, $botcash);
			
			$this->msg = "";
			
		}
		
		public function getProps () {
			echo 'cash: ' . $this->cash . '<br />';
			echo 'contrib: ' . $this->contrib . '<br />';
			echo 'idx: ' . $this->idx . '<br />';
			echo 'hf: ' . $this->hf . '<br />';
			echo 'vol: ' . $this->vol . '<br />';
			echo 'vcop: ' . $this->vcop . '<br />';
			echo 'vrpop: ' . $this->vrpop . '<br />';
			echo 'cop: ' . http_build_query($this->cop, '', ', ') . '<br />';
			echo 'rpop: ' . http_build_query($this->rpop, '', ', ') . '<br />';
			
			
			
			$m = 0.5;	
			$op = [];
			echo 'Lop: '.(0.25 + 0.5 * $m).'<br/>';
			foreach ($this->cop as $idx => $cop) {
				if ($idx != $this->idx){
					$op[$idx] = ($cop + $this->rpop[$idx] * $m);
				}
			} unset($idx, $botcash);
			echo 'OP: ' . http_build_query($op, '', ', ') . '<br />';
			echo 'Hop: '.(0.8 + 0.5 * $m).'<br/>';
			echo 'cooldown: ' . implode(', ', $this->cooldown) . '<br />';
			
			echo '<br /><br /><br />'. $this->msg;
		}
		
		public function call ($soc) {
			$this->contrib = $this->hf * $this->cash;
			return $this->contrib;
		}
		
		public function payoff_listener ($soc, $payoff){
			global $args;
			$this->vol += ($this->contrib - $payoff) / (1 + $this->cash) * $this->vol;
			$this->vol = bound($args['g']['min_vol'], $this->vol, $args['g']['max_vol']);
			
			$avgcratio = 0;
			foreach ($soc->Cash as $idx => $botcash) {
				if ($idx != $this->idx) {
					$avgcratio += ($soc->Contrib[$idx] /(1 + $botcash));
					$this->cop[$idx] = $this->cop[$idx] * (1 - $this->vcop * $this->vol) + ($soc->Contrib[$idx] / (1 + $botcash)) * ($this->vcop * $this->vol);
				}
			}unset($idx, $botcash);
			$avgcratio /= ($soc->pop - 1);
			$this->hf = bound(0, $this->hf * (1 - $this->vol) + $avgcratio * $this->vol, 1);
			
		}
		
		
		public function request_rp ($soc) {
			//
			// run after the contrib has been subtracted from the cash
			//
			global $args, $rp_factor;
			
			$rp = [];
			
			$p = ($this->contrib > $args['g']['minselfc'] * $this->cash) ? true : false;			// punish only if self contrib > min
			$rploss = 0;
			$iter = shuffle_assoc($soc->Cash);
			foreach ($iter as $idx => $botcash) {
				if ($idx != $this->idx){
					if ($this->cooldown[$idx] == 0){
						if ($rploss > $args['g']['max_rploss']) {
							$rp[$idx] = 0;
						} else {
							if ($p && $this->cop[$idx] + $this->rpop[$idx] * $args['g']['m'] < 0.25 + 0.5 * $args['g']['m']) {
								$rp[$idx] = - $args['g']['p0'] * $botcash * (1 + $this->vol);						// neg val => punish
								$rploss += $args['g']['p0'] * $botcash * $rp_factor * (1 + $this->vol);
								$this->cooldown[$idx] = 1;
							}
							elseif ($this->cop[$idx] + $this->rpop[$idx] * $args['g']['m'] > 0.8 + 0.5 * $args['g']['m']) {
								$rp[$idx] = $args['g']['r0'] * $botcash * (1 + $this->vol);						// pos val => reward/transfer cash
								$rploss += $args['g']['r0'] * $botcash * ($rp_factor + 1) * (1 + $this->vol);	// as one reward takes the space of 1/rpf punishes
								$this->cooldown[$idx] = 2;														// in case of a break, n(r) ~ rpf * n(p) as should be
							}								
							else {
								$rp[$idx] = 0;
							}
						}
					} else {
						$rp[$idx] = 0;
						$this->cooldown[$idx]++;
						if ($this->cooldown[$idx] >= $args['g']['coolrnds']) {
							$this->cooldown[$idx] = 0 ;	// cooldown rounds -> 2 or 3
						}
					}
				} else {
					$rp[$idx] = 0;
				}
			} unset($idx, $botcash);
			return $rp;
		}
		
		public function rp_listener ($soc, $rp){
			//
			// run immediately after request_rp()
			// $rp is an array of idx => [idx => rpval]
			//
			
			global $args, $rp_factor;
			
			$netrp = 0;
			$netabsrp = 0;
			foreach ($rp as $Midx => $rprow) {		// Midx(major idx) -> executor's idx, idx -> reciever's idx
				if ($Midx != $this->idx) {
					foreach($rprow as $idx => $rpval) {
						if ($rpval > 0) {
							$rpopswing = (1 / $rp_factor) * ($rpval / (1 + $soc->Cash[$idx] + $rpval)) * ($this->vrpop * $this->vol);
						}
						else {							
							$rpopswing = ($rpval / (1 + $soc->Cash[$idx])) * ($this->vrpop * $this->vol);
						}
						if ($idx == $this->idx) {
							$netrp += $rpval;
							$netabsrp += abs($rpval);
							$this->rpop[$Midx] += $rpopswing;
							$this->rpop[$Midx] = bound(0, $this->rpop[$Midx], 1);
						} else {
							// someone else is punished / rewarded
							$this->rpop[$Midx] += $rpopswing * ($this->cop[$idx] - 0.5) / 2;		// if a guy with poor contributions is punished, improve the op of the punisher
						}
					} unset ($idx, $rpval);
				}
			}unset($Midx, $rprow);
			
			$eta = $netrp / ($netabsrp + $this->cash + 1);				// typically much < 1
			$this->vol = bound($args['g']['min_vol'], $this->vol - $eta, $args['g']['max_vol']);
			
			$this->hf = bound(0, ($this->hf * (1 - $this->vol * $eta * $eta * $args['g']['vol_rp_const']) + $this->vol * $eta * $eta * $args['g']['vol_rp_const'] * (($eta > 0) ?1:0)), 1);
		}
	}
	
	class Bad implements Bot {
		
		public $cash;
		public $contrib;
		private $idx;
		
		private $hf;
		private $vol;
		
		private $cop;				// cop measures contrib
		private $rpop;
		private $cashop;			// measures cash
		
		private $vcop;
		private $vrpop;
		private $vcashop;
		
		
		public function __construct ($soc, $idx) {
			
			global $args;
			
			$this->cash = $soc->icash;
			$this->contrib = 0;
			
			$this->idx = $idx;
			
			$this->hf = randf(0, 0.2);
			$this->vol = randf(0.05, 0.25);
				
			$this->vcop = randf(0.5,1);
			$this->vrpop = randf(2,3);
			$this->vcashop = randf(1,2);
			
			$this->cop = [];
			$this->rpop = [];
			$this->cashop = [];
			
			foreach ($soc->Cash as $botidx => $botcash) {
				if ($botidx != $this->idx) {
					$this->cop[$botidx] = $args['b']['icop'];
					$this->rpop[$botidx] = $args['b']['irpop'];
					$this->cashop[$botidx] = 1;						// all have equal cash
				}
			} unset($botidx, $botcash);
			
		}
		
		public function getProps () {
			echo 'cash: ' . $this->cash . '<br />';
			echo 'contrib: ' . $this->contrib . '<br />';
			echo 'idx: ' . $this->idx . '<br />';
			echo 'hf: ' . $this->hf . '<br />';
			echo 'vol: ' . $this->vol . '<br />';
			echo 'vcop: ' . $this->vcop . '<br />';
			echo 'vrpop: ' . $this->vrpop . '<br />';
			echo 'cop: ' . http_build_query($this->cop, '', ', ') . '<br />';
			echo 'rpop: ' . http_build_query($this->rpop, '', ', ') . '<br />';
		
			$m = 2;
			$op = [];
			echo 'Lop: '.(0 + 0.5 * $m).'<br/>';
			foreach ($this->cop as $idx => $cop) {
				if ($idx != $this->idx){
					$op[$idx] = ($cop + $this->rpop[$idx] * $m);
				}
			} unset($idx, $botcash);
			echo 'OP: ' . http_build_query($op, '', ', ') . '<br />';
			echo 'Hop: '.(1 + 0.625 * $m).'<br/>';
		}
		
		public function call ($soc) {
			$this->contrib = $this->hf * $this->cash;
			return $this->contrib;
		}
	
		public function payoff_listener ($soc, $payoff){
			//
			// vol, cop std behaviour
			// hf driven by cr of the richest guy
			//
			
			global $args;
			$this->vol += ($this->contrib - $payoff) / (1 + $this->cash) * $this->vol;
			$this->vol = bound($args['b']['min_vol'], $this->vol, $args['b']['max_vol']);
			
			$best_cr = 0;
			$max_cash = 0;
			foreach ($soc->Cash as $idx => $botcash) {
				if ($botcash > $max_cash) {
					$max_cash = $botcash;
					$best_cr = $soc->Contrib[$idx] / $botcash;
				}
				
				if ($idx != $this->idx) {
					$this->cop[$idx] = bound(0, $this->cop[$idx] * (1 - $this->vcop * $this->vol) + ($soc->Contrib[$idx] / (1 + $botcash)) * ($this->vcop * $this->vol),1);
				}
			} unset($idx, $botcash);
			
			$this->hf = bound(0, $this->hf * (1 - $this->vol) + $best_cr * $this->vol, 1);
			
		}
		
		public function request_rp ($soc) {
			//
			// never punish top 3 richest guys, less likely to punish other top guys
			//
			
			global $args, $rp_factor;
			
			$rp = [];
			
			// get max individual cash
			$max_cash = 0;
			foreach ($soc->Cash as $idx => $botcash) {
				if ($botcash > $max_cash) $max_cash = $botcash;
			}
			
			
			$lbd = getTop($soc->Cash, 3);
			$rploss = 0;
			
			$iter = shuffle_assoc($soc->Cash);
			foreach ( $iter as $idx => $botcash) {
				$this->cashop[$idx] = ($botcash+1)/(1+$max_cash);
				if ($idx != $this->idx && !in_array($idx, $lbd)) {
					// multiple calls of in_array inefficient, look at array_combine, array_diff_key
					
					if ($rploss > $args['b']['max_rploss']) {
						$rp[$idx] = 0;
					} else {
						if (($this->cop[$idx] + $this->rpop[$idx] * $args['b']['m1'] + $this->cashop[$idx] * $args['b']['m2']) < (0 + 0.5 * $args['b']['m1'] + 0.5 * $args['b']['m2'])) {
							$rp[$idx] = (($this->cop[$idx] + $this->rpop[$idx] * $args['b']['m1'] + $this->cashop[$idx] * $args['b']['m2']) - (0.25 + 0.5 * $args['b']['m1'] + 0.5 * $args['b']['m2'])) * $args['b']['p0'] * $botcash;
							$rploss += (-$rp[$idx]) * $rp_factor;
						}
						elseif ($this->cop[$idx] + $this->rpop[$idx] * $args['b']['m1'] + $this->cashop[$idx] * $args['b']['m2'] > (1 + 0.625 * $args['b']['m1'] + 0.8 * $args['b']['m2'])) {
							$rp[$idx] = $args['b']['r0'] * $botcash * ($this->cop[$idx] + $this->rpop[$idx] * $args['b']['m1'] + $this->cashop[$idx] * $args['b']['m2'] - (1 + 0.625 * $args['b']['m1'] + 0.8 * $args['b']['m2']));
							$rploss += ($rp[$idx]) * ($rp_factor + 1);
						} else {
							$rp[$idx] = 0;
						}
					}
				} else {
					$rp[$idx] = 0;
				}
			}
			return $rp;
		}
			
		public function rp_listener ($soc, $rp){
			//
			// occurs before punishment is actually executed, that is before the cash is affected
			//
			global $args, $rp_factor;
			
			$netrp = 0;
			
			foreach ($rp as $Midx => $rprow) {		// Midx(major idx) -> executor's idx, idx -> reciever's idx
				if ($Midx != $this->idx) {
					foreach($rprow as $idx => $rpval) {
						if ($idx == $this->idx) {
							$netrp += $rpval;
							$this->rpop[$Midx] += (($rpval > 0)? (1 / $rp_factor): 1)*($rpval / (1 + $this->cash)) * ($this->vrpop * $this->vol);
							$this->rpop[$Midx] = bound(0, $this->rpop[$Midx], 1);
						}
					} unset ($idx, $rpval);
				}
			}unset($Midx, $rprow);
			
			
			$eta = bound(-1,$netrp / ($this->cash + 1),1) / $args['b']['hf_loss_eq'];
			
			
			// reward -> more volatility (darkness shaken)
			// punishment -> less volatility (darkness confirmed)
			// heavy punishment -> hf rise  (at punishments of $args['b']['hf_loss_eq'] hf rises by $args['b']['hf_loss_eq'], cubic curve)
			if ($eta < 0) {
				$this->hf += ($eta * $eta * $eta) * (-$args['b']['hf_loss_eq']);
				$this->hf = bound(0, $this->hf, 1);
			}
			$this->vol = bound($args['b']['min_vol'], $this->vol * (1 + $eta * $args['b']['vol_rp_const']), $args['b']['max_vol']);
			
		}
	}
	
	/* Society */
	class Society {
		
		private $gr;
		private $cxn;
		private $state;
		
		private $balance;
		private $mf;
		
		public $icash;
		
		public $Cash;
		public $Contrib;
		public $RP;
		
		private $Bots;
		public $pop;
		
		public $ok;
		public $logger;
		
		public function __construct($gr, $state, $icash) {
			
			global $hostname, $username, $password, $dbname;
			
			if ($ok = (preg_match("/^[a-z0-9]{8}$/", $gr) === 1)){
				$this->gr = $gr;
				$this->cxn = new mysqli($hostname, $username, $password, $dbname);
				
				$this->mf = 1.5;
				$this->balance = 0;
				
				$this->icash = $icash;
				
				if ($this->cxn->connect_errno) {
					$this->logger['msg'] = "connection error: " . $this->cxn->connect_error;
					$this->ok = false;
					return;
				}
				
				if ($state == 0) {
					// game just started, contribution inputs taken for the first round: contrib-payoff stage
					$query = "SELECT `idx`, `type`, `cash`, `contrib` FROM `$gr`";
					
					if ($result = $this->cxn->query($query)) {
						// constructing a bot requires all the indices, so the Bots array is init with zero
						$this->pop = $result->num_rows;
						while($row = $result->fetch_assoc()) {
							$i = intval($row['idx']);
							$this->Bots[$i] = false;
							$this->Cash[$i] = $row['cash'];
						}
						
						$result->data_seek(0);
						while($row = $result->fetch_assoc()) {
							$i = intval($row['idx']);							
							if ($row['type'] == 'x') {
								$this->Contrib[$i] = floatval($row['contrib']);
							} elseif ($row['type'] == 'g') {
								$this->Bots[$i] = new Good($this, $row['idx']);
								$this->Bots[$i]->cash = $row['cash'];
							} elseif ($row['type'] == 'b') {
								$this->Bots[$i] = new Bad($this, $row['idx']);
								$this->Bots[$i]->cash = $row['cash'];
							}
							$this->Cash[$i] = $row['cash'];
						}
						$result->close();
						// after this any zero in the bot array implies a human
					} else {
						$this->logger['msg'] = "query failure: ".$this->cxn->error;
						$this->ok = false;
						return;
					}
					
				} elseif ($state == 1) {
					// contrib - payoff
					$query = "SELECT `idx`, `type`, `cash`, `contrib`, `botobj` FROM `$gr`";
					
					if ($result = $this->cxn->query($query)) {
						$this->pop = $result->num_rows;
						while ($row = $result->fetch_assoc()) {
							
							$i = intval($row['idx']);				// afaik all $row['idx'] are strings
							
							if ($row['type'] == 'x') {
								// humans, get contrib (NOT assumed already checked to be less than cash)
								$this->Contrib[$i] = bound(0, $row['contrib'], $row['cash']);
								$this->Bots[$i] = false;
							} else {
								// some variety of bot, retrieve it
								$this->Bots[$i] = unserialize(base64_decode($row['botobj']));
							}
							$this->Cash[$i] = $row['cash'];
							
						}
						$result->close();
					} else {
						$this->logger['msg'] = "query failure: ".$this->cxn->error;
						$this->ok = false;
						return;
					}
				} elseif ($state == 2) {
					// transfer-punishment 
					$query = "SELECT `idx`, `type`, `cash`, `rp`, `botobj` FROM `$gr`";
					
					if ($result = $this->cxn->query($query)) {
						$this->pop = $result->num_rows;
						while ($row = $result->fetch_assoc()) {
							
							$i = intval($row['idx']);
							
							if ($row['type'] == 'x') {
								// humans, get rp
								$this->RP[$i] = unserialize(base64_decode($row['rp']));
								$this->Bots[$i] = false;
							} else {
								// some variety of bot, retrieve it
								$this->Bots[$i] = unserialize(base64_decode($row['botobj']));
							}
							$this->Cash[$i] = $row['cash'];
						}
						$result->close();
					} else {
						$this->logger['msg'] = "query failure: ".$this->cxn->error;
						$this->ok = false;
						return;
					}
				}
			
			} 
		}
		
		public function __destruct() {
			$this->cxn->close();
		}
		
		private function get_contribs () {
			//
			// every time a human player submits a value, his contrib column gets updated, and his rnd increases in its first decimal
			// once all rnd have reached the final value, this function is called, in it, the contrib column is updated for all bots
			// note: in all cases the cash column is unaffected, and no change in the ui is requested
			// immediately afterwords declare_payoff() is called 
			//
			
			$this->balance = 0; 
			foreach ($this->Bots as $idx => $bot) {
				if ($bot) {
					$this->Contrib[$idx] = bound(0, $bot->call($this), $bot->cash);
				} 
				// elseif human => already init
				$this->balance += $this->Contrib[$idx];
			} unset($idx, $bot);
			$this->balance *= $this->mf;
		}
		
		private function declare_payoff () {
			//
			// occurs in the same backend execution as call()  which means the database values aren't adjusted yet
			// i.e contrib hasn't been subtracted from cash, important as many payoff listeners depend on this fact
			// once done, the database is updated, and the ui's are instructed to animate the reqd stuff
			//
			
			$payoff = $this->balance / count($this->Bots);
			foreach($this->Bots as $idx => $bot) {
				if ($bot) {
					$bot->payoff_listener($this, $payoff);
				}
			} unset($idx, $bot);
			return $payoff;
		}
		
		public function foo () {
			
			global $hostname, $username, $password, $dbname;
			
			// get contributions
			$this->get_contribs();
		
			// update society's (increased) balance and its major round
			$b = $this->balance;
			$gr = $this->gr;
			$uQuery = "UPDATE `meta` SET `last_balance` = $b, `rnd` = `rnd` + 1 WHERE `grid` = '$gr';";
			if (! $this->cxn->query($uQuery)) {
				$this->logger['msg'] = "update meta failed: ". $this->cxn->error;
				$this->logger['query'] = $uQuery;
				return $this->logger;
			}
			
			// inform payoff details to bots, then change their cash
			$payoff = $this->declare_payoff();
			foreach($this->Cash as $idx => &$cash) {
				$cash += $payoff - $this->Contrib[$idx];
				if ($cash < 0.001) $cash = 0;
				if ($this->Bots[$idx]) {
					$this->Bots[$idx]->cash = $cash;
				}
			} unset($idx, $cash);
			
			// update individual bot's values
			$query = "INSERT INTO `$gr` (`idx`, `cash`, `contrib`, `botobj`) VALUES ";
			$qvals = [];
			foreach($this->Cash as $idx => $cash) {
				if (array_key_exists($idx, $this->Bots)) {
					if ($this->Bots[$idx]) {
						$qvals[] = "($idx,$cash," . $this->Contrib[$idx] . ",'" . base64_encode(serialize($this->Bots[$idx])) . "')";
					} else { 					// humans
						$qvals[] = "($idx,$cash," . $this->Contrib[$idx] . ",NULL )";
					}
				} else {
					$return = [
						'looking_for' => $idx,
						'botkeys' => array_keys($this->Bots),
					];
					return $return;
				}
			}
			$query .= implode(",", $qvals);
			$query .= "ON DUPLICATE KEY UPDATE `cash` = VALUES(`cash`), `contrib` = VALUES(`contrib`), `botobj` = VALUES(`botobj`);";
			if (!$this->cxn->query($query)) {
				$this->logger['msg'] = "update failed: ". $this->cxn->error;
				$this->logger['query'] = $query;
				return $this->logger;
			}
			
			return [
				"carr" => $this->Cash,
				"coarr" => $this->Contrib,
				"balance" => $this->balance,
				"logger" => $this->logger,
			];
		}
		
		private function get_rps () {
			foreach ($this->Bots as $idx => $bot) {
				if ($bot) {
					$this->RP[$idx] = $bot->request_rp($this);
				} 
				// elseif human => already init
			} unset($idx, $bot);
		}
		
		private function declare_rps () {
			foreach($this->Bots as $idx => $bot) {
				if ($bot) {
					$bot->rp_listener($this, $this->RP);
				}
			} unset($idx, $bot);
		}
		
		public function bar () {
			global $hostname, $username, $password, $dbname;
			global $rp_factor;
			$gr = $this->gr;
			
			// get rps
			$this->get_rps();
			
			// inform rp details to bots, then change their cash
			$this->declare_rps();
			
			$this->RP = shuffle_assoc($this->RP);
			
			$rp_log = [];
			
			foreach ($this->RP as $Midx => $rprow) {
				foreach ($rprow as $idx => $rpval) {
					$x = ($rpval > 0) ? (1 + $rp_factor) * $rpval : $rp_factor * (-$rpval);		// reversed from frontend
					if (($rpval != 0) && ($this->Cash[$Midx] > $x)) {
						$this->Cash[$idx] += $rpval;
						$this->Cash[$Midx] -= $x;
						
						if ($this->Cash[$idx] < 0.001) $this->Cash[$idx] = 0;
						if ($this->Cash[$Midx] < 0.001) $this->Cash[$Midx] = 0;
						
						
						if ($this->Bots[$Midx]) {
							$this->Bots[$Midx]->cash = $this->Cash[$Midx];
						}
						
						if ($this->Bots[$idx]) {
							$this->Bots[$idx]->cash = $this->Cash[$idx];
						}
						
						$rp_log[] = [
							"midx" => $Midx,
							"idx" => $idx,
							"cm" => $this->Cash[$Midx],
							"ci" => $this->Cash[$idx],
							"rp" => ($rpval < 0),					// if punishment
						];
					}
				}
			}
			
			// update individual bot's values
			$query = "INSERT INTO `$gr` (`idx`, `cash`, `rp`, `botobj`) VALUES ";
			$qvals = [];
			foreach($this->Cash as $idx => $cash) {
				if (array_key_exists($idx, $this->Bots)) {
					if ($this->Bots[$idx]) {
						$qvals[] = "($idx,$cash,'" . base64_encode(serialize($this->RP[$idx])) . "','" . base64_encode(serialize($this->Bots[$idx])) . "')";
					} else { 					// humans
						$qvals[] = "($idx,$cash,'" . base64_encode(serialize($this->RP[$idx])) . "',NULL )";
					}
				} else {
					$return = [
						'looking_for' => $idx,
						'botkeys' => array_keys($this->Bots),
					];
					return $return;
				}
			}
			$query .= implode(",", $qvals);
			$query .= "ON DUPLICATE KEY UPDATE `cash` = VALUES(`cash`), `rp` = VALUES(`rp`), `botobj` = VALUES(`botobj`);";
			if (!$this->cxn->query($query)) {
				$this->logger['msg'] = "update failed: ". $this->cxn->error;
				$this->logger['query'] = $query;
				return $this->logger;
			}
			
			// update its major round
			$rpblob = base64_encode(serialize($rp_log));
			$uQuery = "UPDATE `meta` SET `rnd` = `rnd` + 1, `rp` = '$rpblob' WHERE `grid` = '$gr';";
			if (! $this->cxn->query($uQuery)) {
				$this->logger['msg'] = "update meta failed: ". $this->cxn->error;
				$this->logger['query'] = $uQuery;
				return $this->logger;
			}
			
			return $rp_log;
		}
	}
?>