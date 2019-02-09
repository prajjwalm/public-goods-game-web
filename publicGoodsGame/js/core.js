/* PIXI aliases */
let Application = PIXI.Application,
	loader = PIXI.loader,
	resources = PIXI.loader.resources,
	Sprite = PIXI.Sprite,
	Graphics = PIXI.Graphics,
	Text = PIXI.Text,
	Container = PIXI.Container,
	TextureCache = PIXI.utils.TextureCache;
	
let Rectangle = PIXI.Rectangle;

// render consts
var center = {x:window.innerWidth/4 - 16, y:window.innerHeight/2};
var member_cash_offset = {x: -2, y: -1};
var radius = 270;
var lstart = 40;
var lend = 236;
var uiRadius = 316;
var hBar_maxlen = 50;
var vBar_maxlen = 50;

/* Support functions */
function get_best_performer(society) {
	var best = 0;
	for (var i = 1; i < society.size; i++) {
		if (society.member[i].cash > society.member[best].cash) best = i;
	}
	return best;
}
	
function get_member_by_id(society, id) {
	if (society instanceof BaseSoc) {
		return id - 1;
	} else {
		for (var i = 0; i < this.size; i++) {
			if (society.member[i].id == id) {
				return i;
			}
		}
		return -1;
	}
}
	
/* Object constructors */	

function Simpleton(vol, hf, cash) {
	
	this.strategy = "s";					// describes the type of the member, to be used only by the society object
	this.id = 0;							// assigned when member joins a society

	// members
	this.vol = vol;							// volatility: how fast the guys' state changes, range: [0.01, 0.5] float	
	this.hf = hf;							// honsesty factor: range: [0, 1] float
	this.cash = cash;						// cash: amount the member owns, range: [0, inf) int
	this.contrib = 0;						// previous contribution
	this.opinion = {};						// opinion of others
	
	// constants
	this.cutoff_opinion = 0.2;				// below which the good simpleton will request punishment
	this.punishment_ratio = 0.4;			// fraction of cash which will be sacrificed for punishment (only for good simpletons)
	this.opinion_mf = 1.25;					// multiplicative factor attached with the opinions of others (only for good simpletons)
	this.avghf_mf = 1.25;					// multiplicative factor attached with the honesty of others, ie. a guy with 0.8 contrib is all good
	this.lim = 0.1;							// beyond this punishment hurts
	this.hf_jump = 0.7;						// jump in hf after punishment (wrt volatility)
	this.cutoff_respect = 0.4;				// beyond this some respect is left even after being punished by the guy
	this.opinion_vol = 2;					// mf of vol for opinions
	this.max_vol = 0.5;
	this.min_vol = 0.01;
	this.vol_delta_factor = 0.4;
	
	// functions
	this.init = function (society) {
		for (var i = 0; i < society.size; i++) {
			if (society.member[i].id != this.id)
				this.opinion[society.member[i].id] = 0.5;
		}
	}
	
	this.call = function (society) {
		// decides contribution during call
		this.contrib = this.cash * this.hf;										// contribute as much as he trusts
		return this.contrib;
	}
	
	this.payoff_listener = function (society, cash_back) {
		// IMPORTANT: must occur after cash is collected and before it is redistributed
		
			// volatility: profit leads to stability, loss to more fluctuaion
		this.vol += this.vol * (this.contrib - cash_back) / (this.contrib + this.cash) * this.vol_delta_factor;
		this.vol = bound(this.min_vol, this.vol, this.max_vol);
		
		
			// honesty factor and opinion: 
		var avghf = 0;
		for (var i = 0; i < society.size; i++) {
			let Id = society.member[i].id;
			if (Id != this.id) {
				var contrib_ratio = (society.member[i].contrib + 1) / (society.member[i].cash + society.member[i].contrib + 1);
				avghf += contrib_ratio * this.avghf_mf;
				if (this.hf > 0.5) {
					this.opinion[Id] = bound(0, (this.opinion[Id] * (1 - this.vol * this.opinion_vol) + this.vol * this.opinion_vol * (contrib_ratio * this.opinion_mf)), 1);
				}
			}
		}
		avghf /= (society.size - 1);
		
		if (this.hf > 0.6) {
			// pure simpletons are driven by the honesty of people around them
			this.hf = bound(0, (this.hf * (1 - 1.2 * this.vol) + avghf * 1.2 * this.vol), 1);
		} 
		else {
			// other simpletons will also emulate the most successful guy around them and respond lesser to public good
			var best = get_best_performer(society);
			peerhf = society.member[best].contrib / (society.member[best].contrib + society.member[best].cash);
			for (var i = 0; i < society.size; i++) {
				let Id = society.member[i].id;		
				if (Id != this.id) {
					let Id = society.member[i].id;
					var cash_ratio = society.member[i].cash / society.member[best].cash;
					this.opinion[Id] = bound(0, (this.opinion[Id] * (1 - this.vol) + this.vol * cash_ratio), 1);
				}
			}
			this.hf = (this.hf * (1 - this.vol) + peerhf * this.vol);
		}
	}
	
	this.request_punishment = function (society){
		var list = {};
		var n = 0;
	
		if (this.hf > 0.4) {
			// only good simpletons will request_punishment
			print(this.id + ": ");
			for (var Id in this.opinion) {
				if (this.opinion.hasOwnProperty(Id)) {
					// print(this.opinion[Id] + ", ");
					if (this.opinion[Id] < this.cutoff_opinion){
						list[Id] = 1;
						n += 1;
						this.opinion[Id] += this.cutoff_opinion * 1.25;
					}
				}
			}
			print("<br/>");
			for (var Id in list) {
				list[Id] = this.cash * this.punishment_ratio / n;
			}
		}
	
		return list;
	}
	
	this.punishment_listener = function(society, punisherId, loss) {
		// hatred for the punisher
		if (this.opinion[punisherId] > this.cutoff_respect) this.opinion[punisherId] /= 2;
		else this.opinion[punisherId] = 0;

		this.vol = bound(0, this.vol + 0.2, 1);

		// fear causes simpletons to increase contribution
		if (loss > this.lim) {
			this.hf += this.hf_jump * this.vol * loss / (this.cash + loss + 1);
			if (this.hf > 1) this.hf = 1;
		}
	}
}

function Accountant(hf, cash) {
	
	this.strategy = "c";
	this.id = 0;
	
	// members
	this.hf = hf;
	this.contrib = 0;
	this.cash = cash;	
	this.opinion = {};
	
	// constants
	this.vol = 1;
	this.cutoff_opinion = 0.1;
	this.punishment_ratio = 0.3;		// must be < 0.5
	this.opinion_vol = 0.15;
	this.safety = 0.1;
	this.hf_jump = 2;
	this.punishments = 3;
	
	// functions
	this.init = function (society) {
		for (var i = 0; i < society.size; i++) {
			if (society.member[i].id != this.id)
				this.opinion[society.member[i].id] = 0.5;
		}
	}
	
	this.call = function (society) {
		this.contrib = this.cash * this.hf;
		return this.contrib;
	}
	
	this.payoff_listener = function (society, cash_back) {
		// IMPORTANT: must occur after cash is collected and before it is redistributed
	
		// volatility: const
		
		// honsesty factor: exactly equal to percentage contribution in the previous round
		var cash_generated = cash_back * society.size;
		var total_contrib = 0;
		var total_cash = 0;
		for (var i = 0; i < society.size; i++) { total_contrib += society.member[i].contrib; }
		for (var i = 0; i < society.size; i++) { total_cash += society.member[i].cash; }
		var contrib_rate = (total_contrib - this.contrib + 1) / (total_cash - this.cash + 1);
		this.hf = bound(0, contrib_rate, 1);
		
		// opinion: binary segregation on whether contribution is above his own
		for (var i = 0; i < society.size; i++) {
			let Id = society.member[i].id;
			if (Id != this.id) {
				if (society.member[i].contrib / (society.member[i].contrib + society.member[i].cash + 1) >= this.contrib / (this.contrib + this.cash + 1)) {
					this.opinion[Id] += this.opinion_vol;
				}
				else if (society.member[i].contrib / (society.member[i].contrib + society.member[i].cash + 1) <= this.contrib / (this.contrib + this.cash + 1) * 0.9) {
					this.opinion[Id] -= this.opinion_vol;
				}
				this.opinion[Id] = bound(0, this.opinion[Id], 1);
			}
		}
		
		// punishments, increase on profit (more cash to spend)
		if (cash_back > this.contrib) this.punishments += 1;
	}
	
	this.request_punishment = function (society){
		var list = [];
		var target = {};
		if (this.punishments > 0){
			for (var Id in this.opinion) {
				if (this.opinion.hasOwnProperty(Id)) {
					if (this.opinion[Id] < this.cutoff_opinion){
						list.push(Id);												// shortlists guys that qualify for punishment
					}
				}
			}
			if (list.length > 0) {
				var target_id1 = list[Math.floor(Math.random()*list.length)];		// selects one randomly from them
				var target_id2 = list[Math.floor(Math.random()*list.length)];		// either two minor punishments will be dealt, or one major punishment
				this.opinion[target_id1] += this.cutoff_opinion * 1.5;
				this.opinion[target_id2] += this.cutoff_opinion * 1.5;
				target[target_id1] = this.cash * this.punishment_ratio;
				target[target_id2] = this.cash * this.punishment_ratio;
				this.punishments -= 1;
			}
		}
		return target;
	}
	
	this.punishment_listener = function(society, punisherId, loss) {
		// hatred
		this.opinion[punisherId] = 0;

		// fear
		var punisherIdx = get_member_by_id(society, punisherId);
			// if present, guilt makes them perform better for one round
		if (loss > this.lim && this.contrib < society.member[punisherIdx].contrib) this.hf += this.hf_jump * this.vol;
		
	}
}

function Rational(cash) {
	// defined by safe play and envy
	this.strategy = "r";
	this.vol = 0;
	this.id = 0;
	
	this.cash = cash;
	this.contrib = 0;
	this.opinion = {};
	this.punishment_cntr = {};
	this.avg_hf = 0.3;
	this.var_hf = 0.1;
	this.sup_hf = 0.5;
	this.zero_freq = 4;
	this.rfreq = 5;
	this.limloss = 0.33;
	this.cumloss = 0;
	
	this.init = function (society) {
		for (var i = 0; i < society.size; i++) {
			if (society.member[i].id != this.id) {
				this.opinion[society.member[i].id] = 0.5;
				this.punishment_cntr[society.member[i].id] = 0;
			}
		}
	}
	
	this.call = function (society) {
		var x = Math.random();
		if (x < 1 / this.zero_freq) {
			this.contrib = 0;
		} else if (x > 1 - 1 / (2 * this.zero_freq)) {
			this.contrib = this.sup_hf;
		} else {
			this.contrib = (this.avg_hf + x * this.var_hf) * this.cash;
		}
		if (this.contrib > this.cash) this.contrib = this.cash;
		return this.contrib;
	}
	

	this.payoff_listener = function (society, cash_back) {
		if (cash_back < this.contrib) {
			this.avg_hf *= 0.9;
			this.var_hf *= 0.9; 
		} else {
			for (var i = 0; i < society.size; i++) {
				if (society.member[i].id != this.id) {
					this.punishment_cntr[society.member[i].id] -= 0.5;
					if (this.punishment_cntr[society.member[i].id] < 0) this.punishment_cntr[society.member[i].id] = 0;
				}
			}
		}
		// keep opinions (as an excuse for punishment rather than a reason)
		for (var i = 0; i < society.size; i++) {
			let Id = society.member[i].id;
			if (Id != this.id) {
				var contrib_ratio = (society.member[i].contrib + 1) / (society.member[i].cash + society.member[i].contrib + 1);
				if (contrib_ratio < this.avg_hf) {
					this.opinion[Id] = bound(0, this.opinion[Id] - 0.2, 1);
				} else if (contrib_ratio > this.sup_hf){
					this.opinion[Id] = bound(0, this.opinion[Id] + 0.1, 1);
				}
			}
		}
		this.cumloss = 0;
	}
	
	this.request_punishment = function (society) {
		var target = {};
		for (var Id in this.opinion) {
			if (this.opinion.hasOwnProperty(Id)) {
				if (this.punishment_cntr[Id] >= 1){
					target[Id] = this.cash * 0.2;
				}
			}
		}
		
		if (society.r_no % this.rfreq == 0) {
			for (var i = 0; i < 3; i++) {
				let lid = society.member[society.leaderboard[i]].id;
				if (this.opinion[lid] <= 0.2) {
					if (target.hasOwnProperty(lid)) {
						target[lid] += 2 * (0.12 - 0.02 * i) * this.cash;
					} else {
						target[lid] = (0.12 - 0.02 * i) * this.cash;
					}
				}
			}
		}
		return target;
	}
	
	this.punishment_listener = function (society, punisherId, loss) {
		this.punishment_cntr[punisherId]++;
		this.cumloss += loss;
		if (this.cumloss > this.cash * this.limloss) {
			this.avg_hf = 1 - (1 - this.avg_hf) * 0.9;
			this.avg_hf = 1 - (1 - this.avg_hf) * 0.9;
			this.cumloss = 0;
		}
	}
}

function Analyst(vol, cash) {
	// analysts: minimize contribution to maximize profit, but push up morale when reqd to an extent, and are aggressive
	
	// constants
	this.strategy = "a";
	this.max_vol = 0.5;
	this.min_vol = 0.01;
	this.vol_delta_factor = 0.4;
	
	// members
	this.vol = vol;
	this.pavg = 0;
	this.avg = 0;
	this.cutoff = cash * (1 - vol) * 0.2;	// cutoff: when an analyst will be triggered, and how much does he contribute then
	this.pcutoff = cash * (1 - vol) * 0.1;	// revenge when targets have cash over this, ie. enough to hurt
	this.r_expenditure = 0.4;				// percent of cash that may be used for revenge
	this.bait_rate = 0.2;					// factor of cash to be used for bait	
	this.jumps = 4;							// no of non-trivial contribs
	this.contrib = 0;
	this.cash = cash;
	this.wrath_factor = 5;
	this.p_bonus = 0;						// 0.05 after punishment, so as to give the impression of improvement
	this.pacification_factor = 1.36;
	this.n_punishments = 5;
	this.targets = [];
	this.opinion = {};						// either 0 or 1, 0 only if punished by
	this.punishments;
	
	// functions
	this.init = function (society) {
		for (var i = 0; i < society.size; i++) {
			if (society.member[i].id != this.id)
				this.opinion[society.member[i].id] = 1;
		}
		this.punishments = Array(society.size).fill(this.n_punishments);
	}
	
	this.call = function (society) {
		var avg = this.avg;
		this.contrib = this.cash * (0.1 * this.vol * Math.random() + this.p_bonus);
		if (society.r_no > 1) {
			if (this.pavg > avg + this.cutoff || (avg < this.cutoff && this.pavg > this.cutoff)) {
				if (this.jumps > 0){
					this.contrib += avg + this.cutoff;										// decline state: push up morale
					if (this.contrib > this.cash)											// should almost never be true
						this.contrib = this.cash;	
					this.jumps -= 1;
				}
			} 	
		}
		p_bonus = 0;
		return this.contrib;
	}
	
	this.payoff_listener = function (society, cash_back) {
		this.vol += this.vol * (this.contrib - cash_back) / (this.contrib + this.cash) * this.vol_delta_factor;
		this.vol = bound(this.min_vol, this.vol, this.max_vol);
		
		this.cutoff = this.cash * (1 - this.vol) * this.bait_rate;	
		
		this.pavg = this.avg;
		this.avg = (society.balance - this.contrib) / (society.size - 1);
		
		for (var Id in this.opinion){
			if (this.opinion.hasOwnProperty(Id)) {
				var idx = get_member_by_id(society, Id);
				if (this.punishments[idx] == 0){
					if (this.opinion[Id] < 1) this.opinion[Id] += this.pacification_factor * cash_back / (this.cash + 1);
					if (this.opinion[Id] > 1) this.opinion[Id] = 1;
				}
			}
		}
	}
	
	this.request_punishment = function (society) {
		var list = {};
		var n = 0;
		var revenge_cost = 0;
		for (var Id in this.opinion) {
			if (this.opinion.hasOwnProperty(Id)) {
				if (this.opinion[Id] === 0){
					var idx = get_member_by_id(society, Id);
					if (this.punishments[idx] > 0){
						if (revenge_cost < this.cash * this.r_expenditure) {
							if (idx == -1) {
								alert("grave error in analyst request_punishment");
							}
							if (society.member[idx].cash > this.pcutoff) {
								list[Id] = society.member[idx].cash / society.punishment_cost;
								revenge_cost += society.member[idx].cash / society.punishment_cost;
								this.punishments[idx] -= 1;
							}
						}
					}
				}
			}
		}
		return list;
	}
	
	this.punishment_listener = function(society, punisherId, loss) {
		// hatred
		this.opinion[punisherId] -= this.wrath_factor * loss / (this.cash + loss + 1);
		if (this.opinion[punisherId] < 0) this.opinion[punisherId] = 0;
		
		var punisherIdx = get_member_by_id(society, punisherId);
		if (this.punishments[punisherIdx] === 0) {
			this.punishments[punisherIdx] = 3;
		}
		// fear: none
		this.p_bonus = 0.05;
		this.jumps -= 1;
		if (this.jumps < 0) this.jumps = 0;
	}
}

function Human(cash) {
	this.strategy = "x";
	this.cash = cash;
	this.contrib = 0;
	this.val = 0;
	this.plist = {};
	
	this.set_val = function (val) {this.val = val; }
	this.set_punish = function (plist) {this.plist = plist; }
	
	this.init = function(society) {}
	
	this.call = function (society) {
		this.contrib = this.val;
		return this.contrib;
	}
	
	this.payoff_listener = function (society, cash_back) {}
	
	this.request_punishment = function (society, cash_back) {return this.plist;}
	
	this.punishment_listener = function (society, cash_back, loss) {}
	
}

function BaseSoc(pop, mf, icash, max_r) {
	// an equal society, all dividents are equally shared (as in a cooperative)
	
	// Member Variables
	this.member = [];
	this.size = pop.ns0 + pop.ns1 + pop.nc + pop.nr + pop.na;

	// shuffle members
	perm = [];
	for (var i = 0; i < pop.ns0; i++) perm.push(0);
	for (var i = 0; i < pop.ns1; i++) perm.push(1);
	for (var i = 0; i < pop.nc; i++) perm.push(2);
	for (var i = 0; i < pop.na; i++) perm.push(3);
	for (var i = 0; i < pop.nr; i++) perm.push(4);
	perm = shuffle(perm);

	for (var ih = 0; ih < HUMAN; ih++) { this.member.push(new Human(icash)); }
	
	for (var i = 0; i < this.size; i++) {
		switch(perm[i]) {
		case 0:
			this.member.push(new Simpleton(Math.random() * 0.15 + 0.05, Math.random()/10, icash));
			break;
		case 1:
			this.member.push(new Simpleton(Math.random() * 0.15 + 0.05, 1 - Math.random()/10, icash));
			break;
		case 2:
			this.member.push(new Accountant(Math.random() * 0.15 + 0.05, icash));
			break;
		case 3:
			this.member.push(new Analyst(Math.random() * 0.15 + 0.05, icash));
			break;
		case 4:
			this.member.push(new Rational(icash));
			break;
		default:
			alert("Unrecognized character");
		}
	}
	
	this.size+=HUMAN;

	for (var i = 0; i < this.size; i++) this.member[i].id = i+1;	// ie. idx = id - 1; maybe redundant if I make opinion matrices, but the opinion should
																	// be with the guy, not the society
	for (var i = 0; i < this.size; i++) this.member[i].init(this);
	
	this.r_no = 0;
	this.max_r = max_r;
	this.mf = mf;
	this.balance = 0;
	
	this.leaderboard = []
	for (var i = 0; i < this.size; i++) {
		this.leaderboard.push(i);
	}
	
	// Constants
	this.punishment_cost = 0.2;										// how much of the punishment is inficted on the punisher
	this.punish_enable = false;
	this.call_sleep = 75;
	this.payoff_isleep = 250;
	this.payoff_fsleep = 250;										// this is actually half the value
	this.justice_sleep = 200;
	
	// Render Objects, vars and consts
	this.render_complete = false;

	this.peep = [];
	this.inert_line = [];
	this.active_line = [];
	this.interaction_line = [];
	this.interaction_line_act = [];
	this.hBar = [];
	this.vBar = [];
	this.flower_vertex = [];
	this.active_circle = [];
	
	// Member Functions (underscore signifies the backend code, while the function with the same name is a wrapper function)
	this.updateUI = async function (balance = false, member = false, i = this.size, slide = false, slideTo = 0){
		if (balance) {
			// update balance
			if (!slide) {
				$("#playground .api #balance").text("$" + this.balance.toFixed(2));
				$("#playground .api #balance").css({
					"top": (center.y - $("#playground .api #balance").height()/2) + "px",
					"left": (center.x - $("#playground .api #balance").width()/2) + "px"
				});
			} else {
				var gran = 5; 
				var ival = this.balance;
				var fval = slideTo;
				var time = this.payoff_fsleep;
				for (var ii = 0, val = ival; ii < gran; ii++, val += (fval - ival)/gran) {
					$("#playground .api #balance").text("$" + val.toFixed(2));
					$("#playground .api #balance").css({							
						"top": (center.y - $("#playground .api #balance").height()/2) + "px",
						"left": (center.x - $("#playground .api #balance").width()/2) + "px"
					});
					await sleep(time/gran);
				}
				$("#playground .api #balance").text("$0.00");
				$("#playground .api #balance").css({							
					"top": (center.y - $("#playground .api #balance").height()/2) + "px",
					"left": (center.x - $("#playground .api #balance").width()/2) + "px"
				});
			}
		}

		if (member) {
			if (i === this.size) {
				for (var j = 0; j < this.size; j++) {
					// cash
					$("#playground div.api div.dynamic div.member_cash:eq("+j+")").text("$" + this.member[j].cash.toFixed(2));
					let memcash_text_width = $("#playground div.api div.dynamic div.member_cash:eq("+j+")").width();
					let memcash_text_height = $("#playground div.api div.dynamic div.member_cash:eq("+j+")").height();
					$("#playground div.api div.dynamic div.member_cash:eq("+j+")").css({
						"top": (center.y - uiRadius * Math.sin(Math.PI * j * 2/this.size) - memcash_text_height/2 + member_cash_offset.y) + "px",
						"left": (center.x + uiRadius * Math.cos(Math.PI * j * 2/this.size) - memcash_text_width/2 + member_cash_offset.x) + "px"
					});
					
					// honesty and volatility bar
					if (this.member[j].strategy != "x") {
						this.vBar[j].width = this.member[j].vol * vBar_maxlen;
						if (this.member[j].strategy != "a" || this.member[j].strategy != "r")	this.hBar[j].width = this.member[j].hf * hBar_maxlen;
					}
				}
			} else if (i < this.size && i >= 0){
				// cash
				$("#playground div.api div.dynamic div.member_cash:eq("+i+")").text("$" + this.member[i].cash.toFixed(2));
				let memcash_text_width = $("#playground div.api div.dynamic div.member_cash:eq("+i+")").width();
				let memcash_text_height = $("#playground div.api div.dynamic div.member_cash:eq("+i+")").height();
				$("#playground div.api div.dynamic div.member_cash:eq("+i+")").css({
					"top": (center.y - uiRadius * Math.sin(Math.PI * i * 2/this.size) - memcash_text_height/2 + member_cash_offset.y) + "px",
					"left": (center.x + uiRadius * Math.cos(Math.PI * i * 2/this.size) - memcash_text_width/2 + member_cash_offset.x) + "px"
				});
				
				if (this.member[i].strategy != "x") {
					if (this.member[i].strategy != "a" || this.member[i].strategy != "r") this.hBar[i].width = this.member[i].hf * hBar_maxlen;
					this.vBar[i].width = this.member[i].vol * vBar_maxlen;
				}
			}
		}
	}
	
	this.update_leaderboard = function () {
		this.leaderboard.sort((i, j) => this.member[j].cash - this.member[i].cash);		// desc order
		// console.log(this.leaderboard);
	}
	
	this.call = async function () {
		for (var i = 0; i < this.size; i++){
			// Initial Graphics rendering
			this.active_line[i].visible = true;
			
			// Core
			await sleep(this.call_sleep);
			var c = this.member[i].call(this);
			this.member[i].cash -= c;
			this.balance += c;

			// Final Graphics rendering
			await this.updateUI(balance = true, member = true, i);
			
			this.active_line[i].visible = false;
		}
		
	}
	
	this.payoff = async function () {
		// Initial Graphics rendering
		for (var i = 0; i < this.size; i++) this.active_line[i].visible = true;
		
		// Core 
		this.balance *= this.mf;
		await sleep(this.payoff_isleep);
		await this.updateUI(balance = true);
		await sleep(this.payoff_isleep);
		var cash_back = this.balance / this.size;	
		for (var i = 0; i < this.size; i++) {			
			this.member[i].payoff_listener(this, cash_back);
		}
		for (var i = 0; i < this.size; i++) {
			this.member[i].cash += cash_back;
		}

		// Final Graphics rendering
		await this.updateUI(balance = true, member = true, this.size, slide = true);
		this.balance = 0;		
		
		for (var i = 0; i < this.size; i++) this.active_line[i].visible = false;
		await sleep(this.payoff_fsleep);
	}
	
	this.justice = async function () {
		perm = []
		for (var i = 0; i < this.size; i++) { perm.push(i);	}
		perm = shuffle(perm);
		
		for (var i = 0; i < this.size; i++) {																		// iterate over punisher
			var red_list = this.member[perm[i]].request_punishment(this);
			for (var Id in red_list) {
				if (red_list.hasOwnProperty(Id)) {																	// iterate over punished
					let idx = get_member_by_id(this, Id);
					if (this.member[perm[i]].cash > red_list[Id] * this.punishment_cost && this.member[idx].cash > 0){	// if punishment executable
						// initial graphics rendering
						$("#playground div.api div.dynamic div.member_cash:eq("+perm[i]+")").css({ "color": "#770000"});
						$("#playground div.api div.dynamic div.member_cash:eq("+idx+")").css({ "color": "#ff0000"});
					
						if (perm[i] < idx) {
							var i1 = perm[i];
							var i2 = idx - perm[i] - 1;
						} else {
							var i1 = idx;
							var i2 = perm[i] - idx - 1;
						}
						this.interaction_line_act[i1][i2].visible = true;
					
						// affect cash of punisher and punished
						this.member[perm[i]].cash -= red_list[Id] * this.punishment_cost;						
						this.member[idx].cash -= red_list[Id];
						if (this.member[idx].cash < 0) this.member[idx].cash = 0;
						this.member[idx].punishment_listener(this, this.member[perm[i]].id, red_list[Id]);
						
						
						// if there is a feeling of (un)deserved punishment...
						for (var ii = 0; ii < this.size; ii++) {
							if (this.member[ii].strategy != "x") {
								this.member[ii].opinion[this.member[perm[i]].id] -= (this.member[ii].opinion[Id] - 0.5);
								// if (this.member[ii].strategy === "s") this.member[ii].hf += (this.member[ii].opinion[Id] - 0.5) * 0.1;
								// so opinions can go upto +- 50% based on punishment decisions
								// everyone feels a bit of pity, except for the really bad
								if (ii != perm[i] && ii != idx) {
									this.member[ii].opinion[Id] *= 1.1;
									if (this.member[ii].opinion[Id] > 1) this.member[ii].opinion[Id] = 1;
								}
							}
						}
						
						this.updateUI(balance = false, member = true, perm[i]);
						this.updateUI(balance = false, member = true, idx);
						
						if (this.member[idx].strategy === "x") {
							await sleep(this.justice_sleep * 2);
						} else {
							await sleep(this.justice_sleep);
						}
						
						// final graphics rendering
						$("#playground div.api div.dynamic div.member_cash:eq("+perm[i]+")").css({ "color": "#000"});
						$("#playground div.api div.dynamic div.member_cash:eq("+idx+")").css({ "color": "#000"});
						this.interaction_line_act[i1][i2].visible = false;
					}
				}
			}
		}
	}
	
	this.render = function() {						// Sets up the initial display for the society
		// Place the members
		tournament_peep_t = resources["assets/images/tournament_peep.json"].textures;
		splash_peep_t = resources["assets/images/splash_peep.json"].textures;
		
		if (HATS) {
			for (var i = 0; i < this.size; i++) {
				if (this.member[i].strategy === "s") {
					if (this.member[i].hf > 0.5) this.peep.push(new Sprite(tournament_peep_t["tournament_peep0000"]));
					else this.peep.push(new Sprite(tournament_peep_t["tournament_peep0001"]));
				}
				else if (this.member[i].strategy === "c") this.peep.push(new Sprite(tournament_peep_t["tournament_peep0004"]));
				else if (this.member[i].strategy === "a") this.peep.push(new Sprite(tournament_peep_t["tournament_peep0003"]));
				else if (this.member[i].strategy === "r") this.peep.push(new Sprite(tournament_peep_t["tournament_peep0006"]));
				else if (this.member[i].strategy === "x") this.peep.push(new Sprite(splash_peep_t["splash_peep0000"]));
			}
			
			for (var i = 0; i < this.size; i++) {
				this.peep[i].position.set(center.x + radius * Math.cos(Math.PI * i * 2/this.size), center.y - radius * Math.sin(Math.PI * i * 2/this.size));
				if (this.member[i].strategy === "x") {
					this.peep[i].anchor.set(0.54, 0.56);
					this.peep[i].scale.set(0.21,0.21);
				} else {
					this.peep[i].anchor.set(0.5,0.6);
					this.peep[i].scale.set(0.4, 0.4);
				}
			}
			for (var i = 0; i < this.size; i++) {
				app.stage.addChild(this.peep[i]);
			}
		} else {
			for (var i = 0; i < this.size; i++) {
				this.peep.push(new Sprite(splash_peep_t["splash_peep0000"]));
			}
			
			for (var i = 0; i < this.size; i++) {
				this.peep[i].position.set(center.x + radius * Math.cos(Math.PI * i * 2/this.size), center.y - radius * Math.sin(Math.PI * i * 2/this.size));
				this.peep[i].anchor.set(0.54, 0.56);
				this.peep[i].scale.set(0.23,0.23);
			}
			for (var i = 0; i < this.size; i++) {
				app.stage.addChild(this.peep[i]);
			}
		}

		// Draw connector lines to the centre
		for (var i = 0; i < this.size; i++) {
			this.inert_line.push(new Graphics());
			this.inert_line[i].lineStyle(3, 0xe0e0e0, 1);
			this.inert_line[i].moveTo(center.x + lstart * Math.cos(Math.PI * i * 2/this.size), center.y - lstart * Math.sin(Math.PI * i * 2/this.size));
			this.inert_line[i].lineTo(center.x + lend * Math.cos(Math.PI * i * 2/this.size), center.y - lend * Math.sin(Math.PI * i * 2/this.size));
			this.flower_vertex.push({
				x: center.x + lend * Math.cos(Math.PI * i * 2/this.size),
				y: center.y - lend * Math.sin(Math.PI * i * 2/this.size)
			});
			this.active_line.push(new Graphics());
			this.active_line[i].lineStyle(3, 0xffee00, 1);
			this.active_line[i].moveTo(center.x + lstart * Math.cos(Math.PI * i * 2/this.size), center.y - lstart * Math.sin(Math.PI * i * 2/this.size));
			this.active_line[i].lineTo(center.x + lend * Math.cos(Math.PI * i * 2/this.size), center.y - lend * Math.sin(Math.PI * i * 2/this.size));
			this.active_line[i].visible = false;
			app.stage.addChild(this.inert_line[i]);
			app.stage.addChild(this.active_line[i]);
		}
		
		// Draw interaction lines: IMPORTANT: interaction_line [i][j] joins peep i to peep i+j+1
		for (var i = 0; i < this.size - 1; i++) {
			this.interaction_line.push([]);
			this.interaction_line_act.push([]);
			for (var j = 0; j < this.size - i - 1; j++){
				this.interaction_line[i].push(new Graphics());
				this.interaction_line[i][j].lineStyle(1, 0xe0e0e0, 1);
				this.interaction_line[i][j].moveTo(this.flower_vertex[i].x, this.flower_vertex[i].y);
				this.interaction_line[i][j].lineTo(this.flower_vertex[i+j+1].x, this.flower_vertex[i+j+1].y);
				
				this.interaction_line_act[i].push(new Graphics());
				this.interaction_line_act[i][j].lineStyle(1, 0xff0000, 1);
				this.interaction_line_act[i][j].moveTo(this.flower_vertex[i].x, this.flower_vertex[i].y);
				this.interaction_line_act[i][j].lineTo(this.flower_vertex[i+j+1].x, this.flower_vertex[i+j+1].y);
				this.interaction_line_act[i][j].visible = false;
				app.stage.addChild(this.interaction_line[i][j]);
				app.stage.addChild(this.interaction_line_act[i][j]);
			}
		}
		
		for (var i = 0; i < this.size; i++) {			
			// mention the cash 
			$("#playground div.api div.dynamic").append("<div class = 'member_cash'></div>");
			$("#playground div.api div.dynamic div.member_cash:eq("+i+")").text("$" + this.member[i].cash.toFixed(2));
			let memcash_text_width = $("#playground div.api div.dynamic div.member_cash:eq("+i+")").width();
			let memcash_text_height = $("#playground div.api div.dynamic div.member_cash:eq("+i+")").height();
			let cash_y = (center.y - uiRadius * Math.sin(Math.PI * i * 2/this.size) - memcash_text_height/2 + member_cash_offset.y);
			let cash_x = (center.x + uiRadius * Math.cos(Math.PI * i * 2/this.size) - memcash_text_width/2 + member_cash_offset.x);
			$("#playground div.api div.dynamic div.member_cash:eq("+i+")").css({
				"top": cash_y + "px",
				"left": cash_x + "px"
			});
			
			// draw the honesty bar
			this.hBar.push(new Graphics());					// added even for humans so that array indices are unaffected
			this.hBar[i].position.set((center.x + uiRadius * Math.cos(Math.PI * i * 2/this.size) - memcash_text_width/2 + member_cash_offset.x),
								(center.y - uiRadius * Math.sin(Math.PI * i * 2/this.size) - memcash_text_height/2 + member_cash_offset.y - 2));
								
			if (this.member[i].strategy != "x") {			// turn the new Graphics into a rect if the peep is not a human
				if (this.member[i].strategy != "a" || this.member[i].strategy != "r"){
					this.hBar[i].beginFill(0xFF3300);
					this.hBar[i].drawRect(0,0,hBar_maxlen*(this.member[i].hf + 0.01), 3);
					this.hBar[i].endFill();
				} else {
					this.hBar[i].beginFill(0x888888);
					this.hBar[i].drawRect(0,0,hBar_maxlen, 3);
					this.hBar[i].endFill();
				}

				if (HATS) app.stage.addChild(this.hBar[i]);
			}
			
			// draw the volatility bar 
			this.vBar.push(new Graphics());
			this.vBar[i].position.set((center.x + uiRadius * Math.cos(Math.PI * i * 2/this.size) - memcash_text_width/2 + member_cash_offset.x),
								(center.y - uiRadius * Math.sin(Math.PI * i * 2/this.size) - memcash_text_height/2 + member_cash_offset.y - 6));		
			if (this.member[i].strategy != "x") {
				this.vBar[i].beginFill(0xFFb600);
				this.vBar[i].drawRect(0,0,vBar_maxlen*(this.member[i].vol), 2);
				this.vBar[i].endFill();
				if (HATS) app.stage.addChild(this.vBar[i]);
			}
		}
		
		// Draw activation indicator circles, important: flower_vertex must be complete
		for (var i = 0; i < this.size; i++) {
			this.active_circle.push(new Graphics());
			this.active_circle[i].beginFill(0x42c425);
			this.active_circle[i].drawCircle(this.flower_vertex[i].x, this.flower_vertex[i].y, 4);
			this.active_circle[i].endFill();
			this.active_circle[i].visible = false;
			app.stage.addChild(this.active_circle[i]);
		}
		
		// Draw penalty boxes in play mode, drawn for all peeps, shown only for the other peeps
		for (var i = 0; i < this.size; i++) {
			let pen_offset = {
				"x": -24,
				"y": -54
			}
			let pen_rx = 24;
			$("#penalties").append('<div class="centered" id="pen'+i+'"> <div class="group"><input type="number" id="p'+i+'" min="0" step="any" required="None"/><label for="p'+i+'"> $'+ this.member[i].contrib+'</label><div class="bar"></div></div></div>');
			let pen_y = (center.y - (uiRadius) * Math.sin(Math.PI * i * 2/this.size) + pen_offset.y);
			let pen_x = (center.x + (uiRadius + pen_rx) * Math.cos(Math.PI * i * 2/this.size) + pen_offset.x);
			
			if (i > this.size / 2) {
				pen_y += 50;
			}
			
			$("#penalties #pen"+i+".centered").css({
				"top": pen_y + "px",
				"left": pen_x + "px"
			});
		}
		$("#penalties .centered").hide();

		this.updateUI(balance = true, member = true);		
		this.render_complete = true;
	}
	
	this.autogame = async function (iter) {
		$("#restart_sim").prop('disabled', true);
		if (!this.render_complete){ 
			await this.render();		// execute this first and immediately
		}
		if (iter === 0) {
			while (RUN) {
				this.r_no += 1;
				$("#rno").text(this.r_no);

				await this.call();
				await this.payoff();
				if (this.punish_enable) await this.justice();
				this.update_leaderboard();
				
				if (!RUN) {
					$("#run_sim").prop('disabled', false);
					$("#step_sim").prop('disabled', false);
				}
			}
		}
		else {
			for (var i = 0; i < iter; i++) {
				this.r_no += 1;
				$("#rno").text(this.r_no);
				await this.call();
				await this.payoff();
				if (this.punish_enable)	await this.justice();
			}
		}
		$("#restart_sim").prop('disabled', false);
	}
	
	this.wrap_up = async function (){
		for (var i = this.size; i >= 0; i--) {
			app.stage.removeChild(this.peep[i]);
			app.stage.removeChild(this.inert_line[i]);
			app.stage.removeChild(this.active_line[i]);
			if (i < this.size - 1) {
				for (var j = this.interaction_line[i].length - 1; j >= 0; j--) app.stage.removeChild(this.interaction_line[i][j]);		
				for (var j = this.interaction_line_act[i].length - 1; j >= 0; j--) app.stage.removeChild(this.interaction_line_act[i][j]);
			}
			app.stage.removeChild(this.hBar[i]);
			app.stage.removeChild(this.vBar[i]);
		}
		$("#playground div.api div.dynamic").html("");
		$("#playground #penalties").html("");
		$("#playground .api #balance").text("$0.00");
	} 
	
}

// Render Initial Graphics
var app = new Application({ 
    antialias: true,    // default: false
    transparent: true, 	// default: false
    resolution: 1       // default: 1
  });

/* Initial Begin */
$(Document).ready(function () {
	app.renderer.view.style.position = "absolute";
	app.renderer.view.style.display = "block";
	app.renderer.autoResize = true;
	app.renderer.resize(1080, window.innerHeight);
	
	$("#playground").append(app.view);									// initialize playground graphics
	$("#stop_sim").prop('disabled', true);
	
	loader
		.add("assets/images/splash_peep.png")
		.add("assets/images/splash_peep.json")
		.add("assets/images/sandbox_hats.png")
		.add("assets/images/sandbox_hats.json")
		.add("assets/images/tournament_peep.png")
		.add("assets/images/tournament_peep.json")
		.load(main);	
});

function main() {
	// create population markers
	hats_t = resources["assets/images/sandbox_hats.json"].textures;
	
	var hats = [];
	hats.push(new Sprite(hats_t["peep_hat0001"]));
	hats.push(new Sprite(hats_t["peep_hat0000"]));
	hats.push(new Sprite(hats_t["peep_hat0004"]));
	hats.push(new Sprite(hats_t["peep_hat0003"]));
	hats.push(new Sprite(hats_t["peep_hat0006"]));
	
	for (var i = 0; i < hats.length; i++) {
		hats[i].anchor.set(0.35,0.6);
		hats[i].position.set(800 + 50 * i, window.innerHeight * 0.64);
		hats[i].scale.set(0.4,0.4);
		app.stage.addChild(hats[i]);
	}
	
	// Create Default Society (no humans)
	var activeSoc = new BaseSoc({
		ns0: POP_CNTS[0],
		ns1: POP_CNTS[1],
		nc: POP_CNTS[2],
		na: POP_CNTS[3],
		nr: POP_CNTS[4]
	}, 1.5, 100, 20);
	if (!activeSoc.render_complete){ 
		activeSoc.render();
	}
	
	
	var Hidx = [];			// to store the human controlled indices
	
	// initialize the switches
	if (HUMAN > 0) {
		for (var i = 0; i < activeSoc.size; i++) {
			if (activeSoc.member[i].strategy === "x") {
				Hidx.push(i);
				if (Hidx.length === HUMAN) {
					break;
				}
			}
		}
		$("#ok").prop('disabled', false);
		$("#run_sim").prop('disabled', true);
		$("#stop_sim").prop('disabled', true);
		$("#step_sim").prop('disabled', true);
		var mangame_state = "call";
	} else {
		var mangame_state = "off";
		$("#ok").prop('disabled', true);
	}
	
	
	// user action responses
	$("#stop_sim").click(function () {
		$("#stop_sim").prop('disabled', true);					// the run, step and restart buttons are enabled at the end of the round
		RUN = false;
	});
	
	$("#run_sim").click(function () {
		if (HUMAN == 0 && !RUN) {
			RUN = true;
			activeSoc.autogame(0);
			$("#run_sim").prop('disabled', true);
			$("#step_sim").prop('disabled', true);
			$("#stop_sim").prop('disabled', false);
		}
	});
	
	$("#step_sim").click(async function () {
		if (!activeSoc.render_complete){ 
			await activeSoc.render();
		}
		$("#run_sim").prop('disabled', true);
		$("#step_sim").prop('disabled', true);
		if (HUMAN == 0 && !RUN) {
			// one round of activeSoc.autogame()
			activeSoc.autogame(1);
		}
		$("#run_sim").prop('disabled', false);
		$("#step_sim").prop('disabled', false);
	});
	
	$("#restart_sim").click(async function () {
		RUN = false;
		_pop_refresh_reqd = false;
		
		$("#stop_sim").prop('disabled', true);
		
		await activeSoc.wrap_up();
		
		if (!$("#nplayers input[type=number]").val() ) {
			HUMAN = 0;
		} else {
			HUMAN = parseInt($("#nplayers input[type=number]").val());			// if there is a change to the number of human players
		}
		
		Hidx = []
		
		activeSoc = await new BaseSoc({
			ns0: POP_CNTS[0],
			ns1: POP_CNTS[1],
			nc: POP_CNTS[2],
			na: POP_CNTS[3],
			nr: POP_CNTS[4]
		}, 1.5, 100, 20);
		if (HUMAN > 0){
			for (var i = 0; i < activeSoc.size; i++) {
				if (activeSoc.member[i].strategy === "x") {
					Hidx.push(i);
					if (Hidx.length === HUMAN) {
						break;
					}
				}
			}
		}
		activeSoc.punish_enable = PUNISH;
		activeSoc.render();
		
		if (HUMAN > 0) {
			$("#step_sim").prop('disabled', true);
			$("#stop_sim").prop('disabled', true);
			$("#run_sim").prop('disabled', true);
			RUN = true;
			mangame_state = "call";
			$("#ok").prop('disabled', false);
			activeSoc.active_circle[Hidx[0]].visible = true;
		} else {
			$("#run_sim").prop('disabled', false);
			$("#step_sim").prop('disabled', false);
			mangame_state = "off";
			$("#ok").prop('disabled', true);
		}
		$("#rno").text(activeSoc.r_no);
	});
	
	var click_cntr = 0;
	
	$("#ok").click(async function () {				// kept outside activeSoc due to scope issues that occur, namely the click function looses access to the
													// activeSoc's this pointer
		$("#ok").prop('disabled', true);
		if (mangame_state == "off")	return; 		// the button should also be disabled in this case
		
		else if (mangame_state == "call") {
			if (activeSoc == null) alert("activeSoc == undefined/null");
			else {
				
				if (Hidx == null) {
					alert("No humans partaking");
				} else if (Hidx.length != HUMAN){
					alert("incorrect number of humans recorded: " + Hidx.length + ", " + HUMAN);
				}
								
				// collect cash from click_cntr ^th human who will be at position i
				if (activeSoc.member[Hidx[click_cntr]].strategy != "x") {
					alert("Some weird error occured around line 1070");
				}
				else {
					if (!$("#contrib #cinput").val()) {
						warn("No input provided, proceeding with zero");
						activeSoc.member[Hidx[click_cntr]].set_val(0);
					} else {
						activeSoc.member[Hidx[click_cntr]].set_val(parseFloat($("#contrib #cinput").val()));
						if (activeSoc.member[Hidx[click_cntr]].val > activeSoc.member[Hidx[click_cntr]].cash) {
							activeSoc.member[Hidx[click_cntr]].val = activeSoc.member[Hidx[click_cntr]].cash;
						} 
						inform("Contribution taken as " + activeSoc.member[Hidx[click_cntr]].val);
					}
				}
				activeSoc.active_circle[Hidx[click_cntr]].visible = false;
				
				$("#contrib #cinput").val(null);
				
				click_cntr++;
				if (click_cntr === HUMAN) {
					// all inputs taken, call the round
					click_cntr = 0;
					await activeSoc.call();
					await activeSoc.payoff();
					activeSoc.r_no++;
					$("#rno").text(activeSoc.r_no);
					
					if (activeSoc.punish_enable) {
						mangame_state = "punish";

						for (var i = 0; i < activeSoc.size; i++) $("#pen"+i+" label").text("$" + activeSoc.member[i].contrib.toFixed(2));
						
						// show the penalty options for the first human (click_cntr = 0)
						$("#penalties .centered").not(":eq("+Hidx[click_cntr]+")").show();
						
					} else {
						activeSoc.update_leaderboard();
						activeSoc.active_circle[Hidx[click_cntr]].visible = true;
					}
				} else {
					activeSoc.active_circle[Hidx[click_cntr]].visible = true;
				}
			}
		} else if (mangame_state == "punish") {
			
			var pcheck = 0;
			var pobj = {};
			for (var i = 0; i < activeSoc.size; i++) {
				if (i != Hidx[click_cntr]) {
					var pi = parseFloat($("#penalties .centered .group #p" + i).val());				
					if (pi) {
						pcheck += pi * activeSoc.punishment_cost;
						pobj[activeSoc.member[i].id] = pi;
					}
				}
			}
			
			for (let i = 0; i < activeSoc.size; i++) {
				$("#penalties .centered .group #p" + i).val(null);
			}
			if (pcheck > activeSoc.member[Hidx[click_cntr]].cash) {
				for (var Id in pobj) {
					if (pobj.hasOwnProperty(Id)) {
						pobj[Id] /= (pcheck / activeSoc.member[Hidx[click_cntr]].cash);
					}
				}
				pcheck = activeSoc.member[Hidx[click_cntr]].cash;
			}
			activeSoc.member[Hidx[click_cntr]].set_punish(pobj);
			
			$("#penalties .centered").not(":eq("+Hidx[click_cntr]+")").hide();
			click_cntr++;
			if (click_cntr == HUMAN) {
				click_cntr = 0;
				await activeSoc.justice();
				activeSoc.update_leaderboard();
				mangame_state = "call";
				activeSoc.active_circle[Hidx[0]].visible = true;
				$("#penalties .centered").hide();
			} else {
				$("#penalties .centered").not(":eq("+Hidx[click_cntr + 1]+")").show();
			}
		}
		$("#ok").prop("disabled",false);
	});
	
	$("#playground .api #control #punishment.switch input[type=checkbox]").click(function () {
		if ($("#playground .api #control #punishment.switch input[type=checkbox]").prop('checked')) {
			$("#playground .api #control #punishment.switch .desc ").text("Punishment Enabled");
			PUNISH = true;
			activeSoc.punish_enable = true;
		} else {
			$("#playground .api #control #punishment.switch .desc ").text("Punishment Disabled");
			PUNISH = false;
			activeSoc.punish_enable = false;
		}
	});
	
	$("#playground .api #control #player.switch input[type=checkbox]").click(function () {
		warn("Restart Required");
		if ($("#playground .api #control #player.switch input[type=checkbox]").prop('checked')) {
			$("#playground .api #control #player.switch .desc ").text("Manual Player ON");
			HUMAN = 1;
			$("ok").prop('disabled', false);
		} else {
			$("#playground .api #control #player.switch .desc ").text("Manual Player OFF");
			HUMAN = 0;
			$("ok").prop('disabled', true);
		}
	});
	
	$("#playground .api #control #nplayers input[type=number]").change(function () {
		var h;
		if (!$("#playground .api #control #nplayers input[type=number]").val()) {
			h = 0;
		} else {
			h = parseInt($("#playground .api #control #nplayers input[type=number]").val());
		}
		if (HUMAN != h) {
			HUMAN = h;
			if (!_pop_refresh_reqd) {
				_pop_refresh_reqd = true;
				warn("Restart Required");
			}
		}
	});
	$("#playground .api #control #nplayers input[type=number]").keyup(function () {
		var h;
		if (!$("#playground .api #control #nplayers input[type=number]").val()) {
			h = 0;
		} else {
			h = parseInt($("#playground .api #control #nplayers input[type=number]").val());
		}
		if (HUMAN != h) {
			HUMAN = h;
			if (!_pop_refresh_reqd) {
				_pop_refresh_reqd = true;
				warn("Restart Required");
			}
		}
	});
	
	$("#playground .api #control #hats.switch input[type=checkbox]").click(function () {
		warn("Restart Required");
		if ($("#playground .api #control #hats.switch input[type=checkbox]").prop('checked')) {
			$("#playground .api #control #hats.switch .desc ").text("Identities Visible");
			HATS = true;
		} else {
			$("#playground .api #control #hats.switch .desc ").text("Identities Invisible");
			HATS = false;
		}
	});
}