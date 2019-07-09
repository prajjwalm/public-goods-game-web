<?php
    
    // To diasable direct url access
    if ( $_SERVER['REQUEST_METHOD']=='GET' && realpath(__FILE__) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
        header( $_SERVER["SERVER_PROTOCOL"]." 404 Not Found", TRUE, 404 );
        die( header( 'location: ../index.php' ) );
    }
    
    require_once("path.php");


    interface Bot {
        public function __construct($soc, $idx);
        public function call ($soc);                // return decimal
        public function request_rp ($soc);          // return array
    
        public function payoff_listener ($soc, $payoff);        // return void
        public function rp_listener ($soc, $rp);                // return void
    }

    /* Constants */
    $p_factor = 0.6;
    $r_factor = 1.0;

    /* Support Functions */
    function randf ($min,$max) {
        return ($min + lcg_value() * ($max - $min));
    }

    function cmp ($x, $y) {
        return -($x <=> $y);
    }

    function getTop($arr, $n) {
        // sorts since $arr size is generally small
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
        "icop"      => 0.5,             // initial contribution opinion
        "irpop"     => 0.5,             // initial reward-punishment opinion
        "ihfl"      => 0.8,             // initial honesty-factor low
        "ihfh"      => 1.0,             // initial honesty-factor high
        "ivcopl"    => 2.0,             // initial volatility of contribution-regd opinion (multiplier) low
        "ivcoph"    => 3.0,             // initial volatility of contribution-regd opinion (multiplier) high
        "ivrpopl"   => 1.0,             // initial volatility of reward-punishment-regd opinion (multiplier) low
        "ivrpoph"   => 3.0,             // initial volatility of reward-punishment-regd opinion (multiplier) high
        "min_vol"   => 0.05,            // minimum volatility (at all times)
        "max_vol"   => 0.5,             // maximum volatility
        "hf_rp_c"   => 2,               // honesty-factor / reward-punishment (fluctuation) const
        "p0"        => 0.2,             // punishment magnitude
        "r0"        => 0.2,             // reward magnitude
        "max_rloss" => 0.2,             // maximum reward loss
        "max_ploss" => 0.4,             // maximum punishment loss
        "copcl"     => 0.3,             // contribution-regd opinion constant low (punishment trigger)
        "rpopcl"    => 0.4,             // reward-punishment-regd opinion constant low
        "copch"     => 0.8,             // contribution-regd opinion constant high (reward trigger)
        "rpopch"    => 0.6,             // reward-punishment-regd opinion constant high
        "m"         => 0.5,             // weight to reward-punishment wrt contribution
    ];

    $b = [
        "icop"      => 0.5,             // initial contribution opinion
        "irpop"     => 0.5,             // initial reward-punishment opinion
        "ihfl"      => 0,               // initial honesty-factor low
        "ihfh"      => 0.2,             // initial honesty-factor high
        "ivcopl"    => 0.5,             // initial volatility of contribution-regd opinion (multiplier) low
        "ivcoph"    => 1.0,             // initial volatility of contribution-regd opinion (multiplier) high
        "ivrpopl"   => 3.0,             // initial volatility of reward-punishment-regd opinion (multiplier) low
        "ivrpoph"   => 4.0,             // initial volatility of reward-punishment-regd opinion (multiplier) high
        "ivcashopl" => 1.0,             // initial volatility of cash-regd opinion (multiplier) low
        "ivcashoph" => 2.0,             // initial volatility of cash-regd opinion (multiplier) high
        "min_vol"   => 0.05,            // minimum volatility (at all times)
        "max_vol"   => 0.5,             // maximum volatility
        "hf_rp_c"   => 0.8,             // honesty-factor / reward-punishment (fluctuation) const
        "p0"        => 0.4,             // punishment magnitude
        "r0"        => 0.2,             // reward magnitude
        "max_rploss" => 0.4,            // maximum reward-punishment loss
        "copcl"     => 0.0,             // contribution-regd opinion constant low (punishment trigger)
        "rpopcl"    => 0.5,             // reward-punishment-regd opinion constant low
        "cashopcl"  => 0.25,            // cash-regd opinion constant low
        "copch"     => 1.0,             // contribution-regd opinion constant high (reward trigger)
        "rpopch"    => 0.625,           // reward-punishment-regd opinion constant high
        "cashopch"  => 0.8,             // cash-regd opinion constant high
        "m1"        => 4,               // weight to reward-punishment wrt contribution
        "m2"        => 1.5,             // weight to cash wrt contribution
        "hf_loss_eq" => 0.8,            // hf loss equal point (hf rises cubically wrt (rp)loss)
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
            
            $this->idx = $idx;
            
            $this->hf = randf($args['g']['ihfl'], $args['g']['ihfh']);
            $this->vol = randf($args['g']['min_vol'] * 2, $args['g']['max_vol'] / 2);
            
            $this->vcop = randf($args['g']['ivcopl'], $args['g']['ivcoph']);
            $this->vrpop = randf($args['g']['ivrpopl'], $args['g']['ivrpoph']);
            
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
            global $args;
            echo 'idx: ' . $this->idx . '<br />';
            echo 'hf: ' . $this->hf . '<br />';
            echo 'vol: ' . $this->vol . '<br />';
            echo 'vcop: ' . $this->vcop . '<br />';
            echo 'vrpop: ' . $this->vrpop . '<br />';
            echo 'cop: ' . http_build_query($this->cop, '', ', ') . '<br />';
            echo 'rpop: ' . http_build_query($this->rpop, '', ', ') . '<br />';
                
            $op = [];
            echo 'Lop: '.($args['g']['copcl'] + $args['g']['rpopcl'] * $args['g']['m']).'<br/>';
            foreach ($this->cop as $idx => $cop) {
                if ($idx != $this->idx){
                    $op[$idx] = ($cop + $this->rpop[$idx] * $args['g']['m']);
                }
            } unset($idx, $cop);
            echo 'OP: ' . http_build_query($op, '', ', ') . '<br />';
            echo 'Hop: '.($args['g']['copch'] + $args['g']['rpopch'] * $args['g']['m']).'<br/>';
            echo 'cooldown: ' . implode(', ', $this->cooldown) . '<br />';
            
            echo '<br /><br /><br />'. $this->msg;
        }
        
        public function call ($soc) {
            return floor($this->hf * $soc->Cash[$this->idx]);
        }
        
        public function payoff_listener ($soc, $payoff){
            global $args;
            $this->vol += ($soc->Contrib[$this->idx] - $payoff) / (1 + $soc->Cash[$this->idx]) * $this->vol;
            $this->vol = bound($args['g']['min_vol'], $this->vol, $args['g']['max_vol']);
            
            $avgcratio = 0;
            foreach ($soc->Cash as $idx => $botcash) {
                if ($idx != $this->idx) {
                    $avgcratio += ($soc->Contrib[$idx] /(1 + $botcash));
                    $this->cop[$idx] = bound(0, $this->cop[$idx] * (1 - $this->vcop * $this->vol) + ($soc->Contrib[$idx] / (1 + $botcash)) * ($this->vcop * $this->vol), 1);
                }
            } unset($idx, $botcash);
            $avgcratio /= ($soc->pop - 1);
            $this->hf = bound(0, $this->hf * (1 - $this->vol) + $avgcratio * $this->vol, 1);
            
        }
                
        public function request_rp ($soc) {
            //
            // run after the contrib has been subtracted from the cash
            // can only request punishment on players currently present (not those who were at some point of time)
            //
            global $args, $r_factor, $p_factor;
            
            $rp = [];
            
            $rloss = 0;
            $ploss = 0;
            $iter = shuffle_assoc($soc->Cash);
            // $this->msg = "";
            foreach ($iter as $idx => $botcash) {
                // $this->msg .= "$idx: ";
                if ($idx != $this->idx){
                    // $this->msg .= "0";
                    if ($this->cop[$idx] + $this->rpop[$idx] * $args['g']['m'] < $args['g']['copcl'] + $args['g']['rpopcl'] * $args['g']['m']) {
                        // $this->msg .= "0";
                        if ($ploss < $args['g']['max_ploss'] * $soc->Cash[$this->idx]) {
                            // $this->msg .= "0";
                            $rp[$idx] = - floor($args['g']['p0'] * $botcash * (1 + $this->vol));                       // neg val => punish
                            $ploss += floor($args['g']['p0'] * $botcash * $p_factor * (1 + $this->vol));
                        } else {
                            // $this->msg .= "1($ploss vs".$args['g']['max_ploss'] * $soc->Cash[$this->idx].")";
                            $rp[$idx] = 0;
                        }
                    }
                    elseif ($this->cop[$idx] + $this->rpop[$idx] * $args['g']['m'] > $args['g']['copch'] + $args['g']['rpopch'] * $args['g']['m']) {
                        // $this->msg .= "1";
                        if ($rloss < $args['g']['max_rloss'] * $soc->Cash[$this->idx]) {
                            // $this->msg .= "0";
                            $rp[$idx] = floor($args['g']['r0'] * $botcash * (1 + $this->vol));                     // pos val => reward/transfer cash
                            $rloss += floor($args['g']['r0'] * $botcash * ($r_factor) * (1 + $this->vol));    // as one reward takes the space of 1/rpf punishes
                        }
                        else {
                            $rp[$idx] = 0;
                        }
                    }                               
                    else {
                        // $this->msg .= "2";
                        $rp[$idx] = 0;
                    }
                } else {
                    // $this->msg .= "1";
                    $rp[$idx] = 0;
                }
                // $this->msg .= "<br />";
            } unset($idx, $botcash);
            return $rp;
        }
        
        public function rp_listener ($soc, $rp){
            //
            // run immediately after request_rp()
            // $rp is an array of idx => [idx => rpval]
            //
            
            global $args, $r_factor, $p_factor;
            
            $netrp = 0;
            $netabsrp = 0;
            foreach ($rp as $Midx => $rprow) {      // Midx(major idx) -> executor's idx, idx -> reciever's idx
                if ($Midx != $this->idx) {
                    foreach($rprow as $idx => $rpval) {
                        if ($rpval > 0) {
                            $rpopswing = ($rpval / (1 + $soc->Cash[$idx] + $rpval)) * ($this->vrpop * $this->vol);
                        }
                        else {                          
                            $rpopswing = ($rpval / (1 + $soc->Cash[$idx])) * ($this->vrpop * $this->vol);           // undefined offset error possible
                        }
                        if ($idx == $this->idx) {
                            $netrp += $rpval;
                            $netabsrp += abs($rpval);
                            $this->rpop[$Midx] += $rpopswing;
                            $this->rpop[$Midx] = bound(0, $this->rpop[$Midx], 1);
                        } else {
                            // someone else is punished / rewarded
                            $this->rpop[$Midx] += $rpopswing * ($this->cop[$idx] - 0.5) / 2;        // if a guy with poor contributions is punished, improve the op of the punisher
                        }
                    } unset ($idx, $rpval);
                }
            } unset($Midx, $rprow);
            
            $eta = $netrp / ($netabsrp + $soc->Cash[$this->idx] + 1);               // typically much < 1
            $this->vol = bound($args['g']['min_vol'], $this->vol - $eta, $args['g']['max_vol']);
            
            $this->hf = bound(0, ($this->hf * (1 - $this->vol * $eta * $eta * $args['g']['hf_rp_c']) + $this->vol * $eta * $eta * $args['g']['hf_rp_c'] * (($eta > 0) ?1:0)), 1);
        }
    }
    
    class Bad implements Bot {
        
        private $idx;
        private $hf;
        private $vol;
        private $cop;               // cop measures contrib
        private $rpop;
        private $cashop;            // measures cash
        private $vcop;
        private $vrpop;
        private $vcashop;
        
        public function __construct ($soc, $idx) {
            global $args;
            $this->idx = $idx;          
            $this->hf = randf($args['b']['ihfl'], $args['b']['ihfh']);
            $this->vol = randf($args['b']['min_vol']*2, $args['b']['max_vol']/2);
            $this->vcop = randf($args['b']['ivcopl'],$args['b']['ivcoph']);
            $this->vrpop = randf($args['b']['ivrpopl'],$args['b']['ivrpoph']);
            $this->vcashop = randf($args['b']['ivcashopl'],$args['b']['ivcashoph']);
            $this->cop = [];
            $this->rpop = [];
            $this->cashop = [];
            
            foreach ($soc->Cash as $botidx => $botcash) {
                if ($botidx != $this->idx) {
                    $this->cop[$botidx] = $args['b']['icop'];
                    $this->rpop[$botidx] = $args['b']['irpop'];
                    $this->cashop[$botidx] = 1;                     // all have equal cash
                }
            } unset($botidx, $botcash);
        }
        
        public function getProps () {
            global $args;
            echo 'idx: ' . $this->idx . '<br />';
            echo 'hf: ' . $this->hf . '<br />';
            echo 'vol: ' . $this->vol . '<br />';
            echo 'vcop: ' . $this->vcop . '<br />';
            echo 'vrpop: ' . $this->vrpop . '<br />';
            echo 'cop: ' . http_build_query($this->cop, '', ', ') . '<br />';
            echo 'rpop: ' . http_build_query($this->rpop, '', ', ') . '<br />';
            echo 'cashop: ' . http_build_query($this->cashop, '', ', ') . '<br />';
        
            $op = [];
            echo 'Lop: '.($args['b']['copcl'] + $args['b']['rpopcl'] * $args['b']['m1'] + $args['b']['cashopcl'] * $args['b']['m2']).'<br/>';
            foreach ($this->cop as $idx => $cop) {
                if ($idx != $this->idx){
                    $op[$idx] = ($cop + $this->rpop[$idx] * $args['b']['m1']  + $this->cashop[$idx] * $args['b']['m2']);
                }
            } unset($idx, $botcash);
            echo 'OP: ' . http_build_query($op, '', ', ') . '<br />';
            echo 'Hop: '.($args['b']['copch'] + $args['b']['rpopch'] * $args['b']['m1'] + $args['b']['cashopch'] * $args['b']['m2']).'<br/>';
        }
        
        public function call ($soc) {
            return floor($this->hf * $soc->Cash[$this->idx]);
        }
    
        public function payoff_listener ($soc, $payoff){
            //
            // vol, cop std behaviour
            // hf driven by cr of the richest guy
            //
            
            global $args;
            $this->vol += ($soc->Contrib[$this->idx] - $payoff) / (1 + $soc->Cash[$this->idx]) * $this->vol;
            $this->vol = bound($args['b']['min_vol'], $this->vol, $args['b']['max_vol']);
            
            $best_cr = 0;
            $max_cash = 0;
            foreach ($soc->Cash as $idx => $botcash) {
                if ($botcash > $max_cash) {
                    $max_cash = $botcash;
                    $best_cr = $soc->Contrib[$idx] / ($botcash + 1);
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
            // can only request punishment on the guys currently present
            //
            
            global $args, $r_factor, $p_factor;
            
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
                    
                    if ($rploss > $args['b']['max_rploss'] * $soc->Cash[$this->idx]) {
                        $rp[$idx] = 0;
                    } else {
                        $x = $this->cop[$idx] + $this->rpop[$idx]*$args['b']['m1'] + $this->cashop[$idx]*$args['b']['m2']; 
                        $l = $args['b']['copcl'] + $args['b']['rpopcl']*$args['b']['m1'] + $args['b']['cashopcl']*$args['b']['m2'];
                        $h = $args['b']['copch'] + $args['b']['rpopch']*$args['b']['m1'] + $args['b']['cashopch']*$args['b']['m2'];
                        if ($x < $l) {
                            $rp[$idx] = floor(($x - $l) * $args['b']['p0'] * $botcash);
                            $rploss += (-$rp[$idx]) * $p_factor;
                        } elseif ($x > $h) {
                            $rp[$idx] = floor(($x - $h) * $args['b']['r0'] * $botcash);
                            $rploss += ($rp[$idx]) * ($r_factor);
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
            global $args, $r_factor, $p_factor;
            
            $netrp = 0;
            
            foreach ($rp as $Midx => $rprow) {      // Midx(major idx) -> executor's idx, idx -> reciever's idx
                if ($Midx != $this->idx) {
                    foreach($rprow as $idx => $rpval) {
                        if ($idx == $this->idx) {
                            $netrp += $rpval;
                            $this->rpop[$Midx] += (($rpval > 0)? $r_factor: $p_factor) * ((1 + $rpval) / (1 + $soc->Cash[$this->idx])) * ($this->vrpop * $this->vol);
                            $this->rpop[$Midx] = bound(0, $this->rpop[$Midx], 1);
                        }
                    } unset ($idx, $rpval);
                }
            }unset($Midx, $rprow);
            
            
            $eta = bound(-1,$netrp / ($soc->Cash[$this->idx] + 1),1) / $args['b']['hf_loss_eq'];
            
            // heavy punishment -> hf rise  (at net punishment of ($args['b']['hf_loss_eq'])*cash, hf rises by $args['b']['hf_loss_eq'])
            if ($eta < 0.4) {
                $this->hf += ($eta * $eta * $eta) * (-$args['b']['hf_loss_eq']);
                $this->hf = bound(0, $this->hf, 1);
            }
            
            // reward -> more volatility (darkness shaken)
            // punishment -> less volatility (darkness confirmed)
            $this->vol = bound($args['b']['min_vol'], $this->vol * (1 + $eta * $args['b']['hf_rp_c']), $args['b']['max_vol']);
        }
    }
    
    /*
    // class calc implements bot {
        
        // public function __construct($soc, $idx);
        
        // public function call ($soc);             // return decimal
        // public function request_rp ($soc);           // return array
    
        // public function payoff_listener ($soc, $payoff);     // return void
        // public function rp_listener ($soc, $rp);                     // return void
    // }*/
    
    /* Society */
    class Society {
        
        private $gr;
        private $cxn;
        private $balance;
        private $mf;        
        private $Bots;
        public $icash;      
        public $Cash;
        public $Contrib;
        public $RP;     
        public $pop;        
        public $ok;
        public $logger;
        
        public function __construct($gr, $state, $icash) {          
            global $hostname, $username, $password, $dbname;
            
            if ($ok = (preg_match("/^[a-z0-9]{8}$/", $gr) === 1)){
                $this->gr = $gr;
                $this->cxn = new mysqli($hostname, $username, $password, $dbname);              
                $this->mf = 2;
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
                                $this->Contrib[$i] = floatval($row['contrib']);         // <cash check pending: done in 
                            } elseif ($row['type'] == 'g') {
                                $this->Bots[$i] = new Good($this, $row['idx']);
                                $this->Contrib[$i] = 0;
                            } elseif ($row['type'] == 'b') {
                                $this->Bots[$i] = new Bad($this, $row['idx']);
                                $this->Contrib[$i] = 0;
                            }
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
                            $i = intval($row['idx']);               // afaik all $row['idx'] are strings                            
                            if ($row['type'] == 'x') {
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
            // every time a human player submits a value, his contrib column gets updated, and his rnd increases
            // once all rnd have reached the final value,  the contrib column is updated for all bots via this
            // note: in all cases the cash column is unaffected, and no change in the ui is requested
            // immediately afterwords declare_payoff() is called 
            //
            
            $this->balance = 0; 
            foreach ($this->Bots as $idx => $bot) {
                if ($bot) {
                    $this->Contrib[$idx] = bound(0, $bot->call($this), $this->Cash[$idx]);
                }
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
            } unset($idx, $cash);
            
            // update individual bot's values
            $query = "INSERT INTO `$gr` (`idx`, `cash`, `contrib`, `botobj`, `type`) VALUES ";
            $qvals = [];
            foreach($this->Cash as $idx => $cash) {
                assert(is_numeric($this->Contrib[$idx]));
                if (array_key_exists($idx, $this->Bots)) {
                    if ($this->Bots[$idx]) {
                        $qvals[] = "($idx,$cash," . $this->Contrib[$idx] . ",'" . base64_encode(serialize($this->Bots[$idx])) . "', 'u')";
                    } else {                    // humans
                        $qvals[] = "($idx,$cash," . $this->Contrib[$idx] . ",NULL , 'u')";
                    }
                } else {
                    $return = [
                        'status' => "error",
                        'looking_for' => $idx,
                        'botkeys' => array_keys($this->Bots),
                    ];
                    return $return;
                }
            } unset($idx, $cash);
            $query .= implode(",", $qvals);
            $query .= "ON DUPLICATE KEY UPDATE `cash` = VALUES(`cash`), `contrib` = VALUES(`contrib`), `botobj` = VALUES(`botobj`), `type` = `type`;";
            if (!$this->cxn->query($query)) {
                $this->logger['msg'] = "update failed: ". $this->cxn->error;
                $this->logger['query'] = $query;
                return $this->logger;
            }
            
            $cash_blob = base64_encode(serialize($this->Cash));
            $contrib_blob = base64_encode(serialize($this->Contrib));
            $balance_val = $this->balance;
            
            $mem_query = "INSERT INTO `mem_$gr` (`cash`, `contrib`, `balance`) VALUES ('$cash_blob', '$contrib_blob', $balance_val);";
            if (!$this->cxn->query($mem_query)) {
                $this->logger['msg'] = "memory update failed: ". $this->cxn->error;
                $this->logger['query'] = $mem_query;
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
            global $r_factor, $p_factor;
            $gr = $this->gr;
            
            //
            // before bots have declared their RP, remove all RPs against non-existent players
            // such RPs may have been submitted by other human players when they were still there
            // NOTE: all bots must decide their RP by iterating through $soc->Cash, so that they 
            // don't submit RP against non-existent players
            //
            
            $rp_log = [];
            foreach ($this->RP as $Midx => $rprow) {
                foreach ($rprow as $idx => $rpval) {
                    if (!array_key_exists($idx, $this->Cash)) {
                        unset($this->RP[$Midx][$idx]);
                    }
                } unset ($idx, $rpval);
            } unset ($Midx, $rprow);
            
            // get rps
            $this->get_rps();
            
            // inform rp details to bots, then change their cash
            $this->declare_rps();
            
            $this->RP = shuffle_assoc($this->RP);
            
            foreach ($this->RP as $Midx => $rprow) {
                foreach ($rprow as $idx => $rpval) {
                    if ($idx != $Midx) {
                        if ($rpval % 25 != 0) { $rpval -= $rpval % 25;}
                        $x = ($rpval > 0) ? 0 : $p_factor * (-$rpval);
                        if (($rpval != 0) && ($this->Cash[$Midx] > $x)) {
                            $this->Cash[$idx] += $rpval;
                            $this->Cash[$Midx] -= $x;

                            if ($this->Cash[$idx] < 0.001) $this->Cash[$idx] = 0;
                            if ($this->Cash[$Midx] < 0.001) $this->Cash[$Midx] = 0;

                            $rp_log[] = [
                                "midx" => $Midx,
                                "idx" => $idx,
                                "cm" => $this->Cash[$Midx],
                                "ci" => $this->Cash[$idx],
                                "rp" => ($rpval < 0),                   // if punishment
                            ];
                        }
                    }
                }
            }
            
            // update individual bot's values
            $query = "INSERT INTO `$gr` (`idx`, `cash`, `rp`, `botobj`, `type`) VALUES ";
            $qvals = [];
            foreach($this->Cash as $idx => $cash) {
                if (array_key_exists($idx, $this->Bots)) {
                    if ($this->Bots[$idx]) {
                        $qvals[] = "($idx,$cash,'" . base64_encode(serialize($this->RP[$idx])) . "','" . base64_encode(serialize($this->Bots[$idx])) . "', 'u')";
                    } else {                    // humans
                        $qvals[] = "($idx,$cash,'" . base64_encode(serialize($this->RP[$idx])) . "',NULL, 'u' )";
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
            $query .= "ON DUPLICATE KEY UPDATE `cash` = VALUES(`cash`), `rp` = VALUES(`rp`), `botobj` = VALUES(`botobj`), `type` = `type`;";
            if (!$this->cxn->query($query)) {
                $this->logger['msg'] = "update failed: ". $this->cxn->error;
                $this->logger['query'] = $query;
                return $this->logger;
            }                                              //  #NORP
            
            // update its major round
            $rpblob = base64_encode(serialize($rp_log));
            $uQuery = "UPDATE `meta` SET `rnd` = `rnd` + 1, `rp` = '$rpblob' WHERE `grid` = '$gr';";
            
            $lastIdxQuery = "SELECT `rnd` FROM `mem_$gr` ORDER BY `rnd` DESC LIMIT 1;";
            $lastIdx = intval((($this->cxn->query($lastIdxQuery))->fetch_assoc())['rnd']);
            $mem_uQuery = "UPDATE `mem_$gr` SET `rp` = '$rpblob' WHERE `rnd` = $lastIdx;";
            
            if ((! $this->cxn->query($uQuery)) || (! $this->cxn->query($mem_uQuery))) {
                $this->logger['msg'] = "update meta failed: ". $this->cxn->error;
                $this->logger['query'] = $uQuery;
                return $this->logger;
            }
            
            return $rp_log;
        }
    }
?>