//////////////////////////////////////////////////////////////////////////////////////////////// GLOBAL SPACE //////////////////

var game_canvas;
var control_canvas;
var game_canvas_DIM;
var game_canvas_X;
var game_canvas_Y;

var activeSoc;
var SocDef = false;
var SocReqd = false;
var mainDone = false;
var pop_mem = [];
var data_mem = [];
var RP_FACTOR = 1/0.6;

var ALL_POSITIONS = {};
var ALL_IDXS = [];

// var HATS = true;			// makes resizing an issue, use in debug only

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

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

var waitForFinalEvent = (function () {
  var timers = {};
  return function (callback, ms, uniqueId) {
    if (!uniqueId) {
      uniqueId = "Don't call this twice without a uniqueId";
    }
    if (timers[uniqueId]) {
      clearTimeout (timers[uniqueId]);
    }
    timers[uniqueId] = setTimeout(callback, ms);
  };
})();


function SocRenderer(pop, data) {

    // render consts
    var center;
    var member_cash_offset;
    var radius;
    var lstart;
    var lend;
    var uiRadius;
    var hBar_maxlen;
    var vBar_maxlen;

    // Constants
    this.punish_enable = false;
    this.call_sleep = 75;
    this.payoff_isleep = 250;
    this.payoff_fsleep = 250;										// this is actually half the value
    this.justice_sleep = 200;

    // Render Objects, vars and consts
    this.size = pop;
    this.render_state = 0;
    this.peep = [];
    this.inert_line = [];
    this.active_line = [];
    this.status_circle = [];
    this.interaction_line = [];
    this.interaction_line_act = [];
    this.interaction_line_pos = [];

    this.hBar = [];
    this.vBar = [];
    this.flower_vertex = [];

    // this.data contains redundant data after first use !
    if (data !== undefined) {
        this.data = data;
    } else this.data = [];

    this.balance = 0.0;
    this.member_cash = [];

    this.updateUI = async function (balance = false, member = false, i = this.size, slide = false, slideTo = 0){
        if (balance) {
            // update balance
            let balance_obj = $("#gamezone #balance");
            if (!slide) {
                balance_obj.text("$" + Math.round(this.balance));
                balance_obj.css({
                    "top": (this.center.y - balance_obj.height()/2) + "px",
                    "left": (this.center.x - balance_obj.width()/2) + "px"
                });
            } else {
                var gran = 5;
                var ival = this.balance;
                var fval = slideTo;
                var time = this.payoff_fsleep;
                for (var ii = 0, val = ival; ii < gran; ii++, val += (fval - ival)/gran) {
                    balance_obj.text("$" + Math.round(val));
                    balance_obj.css({
                        "top": (this.center.y - balance_obj.height()/2) + "px",
                        "left": (this.center.x - balance_obj.width()/2) + "px"
                    });
                    await sleep(time/gran);
                }
                balance_obj.text("$" + Math.round(slideTo));
                balance_obj.css({
                    "top": (this.center.y - balance_obj.height()/2) + "px",
                    "left": (this.center.x - balance_obj.width()/2) + "px"
                });

                this.balance = slideTo;					// only case where balance/member_cash are changed
            }
        }

        if (member) {
            if (i === this.size) {
                for (let j = 0; j < this.size; j++) {
                    // cash
                    $("div.dynamic div.member_cash:eq("+j+") .m_cash").text("$" + Math.round(this.member_cash[j]));
                    let member_cash_j_obj = $("div.dynamic div.member_cash:eq("+j+")");
                    let memcash_text_width = member_cash_j_obj.width();
                    let memcash_text_height = member_cash_j_obj.height();
                    member_cash_j_obj.css({
                        "top": (this.center.y - uiRadius * Math.sin(Math.PI * j * 2/this.size) - memcash_text_height/2 + member_cash_offset.y) + "px",
                        "left": (this.center.x + uiRadius * Math.cos(Math.PI * j * 2/this.size) - memcash_text_width/2 + member_cash_offset.x) + "px"
                    });

                    let roomid = parseInt($("#roomid").text());
                    let roompos = HUMAN_POSITIONS[roomid];

                    if (j == roompos) {
                        $("#contrib .range-slider__range").attr('max', Math.round(this.member_cash[roompos]));

                        // both sliders, contrib and rp
                        $(".range-slider__range").prop('value', 0);
                        $(".range-slider__value").text('0');
                        let cash_deductable = RP_FACTOR*this.member_cash[roompos];

                        let member_cash_alias = this.member_cash;
                        $("#penalties .range-slider__range").each (function (idx) {
                            $(this).attr('min', -Math.round(member_cash_alias[idx]<cash_deductable ? member_cash_alias[idx]:cash_deductable));
                        });
                    }

                }
            } else if (i < this.size && i >= 0){
                // cash

                let roomid = parseInt($("#roomid").text());
                let roompos = HUMAN_POSITIONS[roomid];

                if (i == roompos) {
                    $("#contrib .range-slider__range").attr('max', Math.round(this.member_cash[roompos]));

                    // both sliders, contrib and rp
                    $(".range-slider__range").prop('value', 0);
                    $(".range-slider__value").text('0');
                    let cash_deductable = RP_FACTOR*this.member_cash[roompos];

                    let member_cash_alias = this.member_cash;
                    $("#penalties .range-slider__range").each (function (idx) {
                        $(this).attr('min', -Math.round(member_cash_alias[idx]<cash_deductable ? member_cash_alias[idx]:cash_deductable));
                    });
                }

                $("div.dynamic div.member_cash:eq("+i+") .m_cash").text("$" + Math.round(this.member_cash[i]));
                let member_cash_i_obj = $("div.dynamic div.member_cash:eq("+i+")");
                let memcash_text_width = member_cash_i_obj.width();
                let memcash_text_height = member_cash_i_obj.height();
                member_cash_i_obj.css({
                    "top": (this.center.y - uiRadius * Math.sin(Math.PI * i * 2/this.size) - memcash_text_height/2 + member_cash_offset.y) + "px",
                    "left": (this.center.x + uiRadius * Math.cos(Math.PI * i * 2/this.size) - memcash_text_width/2 + member_cash_offset.x) + "px"
                });
            }
        }
    };

    this.showCircles = function (idxs) {
        for (let i = 0; i < idxs.length; i++) {
            this.status_circle[idxs[i]].visible = true;
        }
    };

    this.hideCircles = function () {
        for (let i = 0; i < this.size; i++) {
            this.status_circle[i].visible = false;
        }
    };

    this.call = async function (contrib, final_cash, balance) {
        //
        // check if inputs are sound else take them in the order of precedence given and log an error
        // contrib and final_cash must be in increasing order of keys
        //

        var err = false;

        var initial_cash = {};

        var icash_arr = [];
        var fcash_arr = [];
        var contrib_arr = [];
        let member_cash_object =$(".dynamic .member_cash");
        var mem_cnt = member_cash_object.length;

        member_cash_object.each(function (index) {
            let i = $(this).css("order");									// string, but that is ok as js objects have string indices anyway
            initial_cash[i] = parseFloat(($(this).text()).slice(1));        // approximate integer value
            dividend = balance/mem_cnt;
            err = err || !approxeq(final_cash[i] - initial_cash[i], dividend - parseFloat(contrib[i]), 1);

            // in case the database indices aren't consequetive
            icash_arr.push(parseFloat(initial_cash[i]));
            fcash_arr.push(parseFloat(final_cash[i]));
            contrib_arr.push(parseFloat(contrib[i]));
        });


        if (err) {
            console.log("error: call received incompatible values");
            console.log(final_cash);
            console.log(balance);
            console.log(contrib);
            console.log(initial_cash);

            let chk_balance = 0;
            for (let i in contrib) {
                if (contrib.hasOwnProperty(i)) {
                    chk_balance += contrib[i];
                }
            }
            chk_balance *= 2; 		// mf: make global

            if (!approxeq(balance, chk_balance, 1)) {
                console.log("fatal error: balance not according to contribs");
                console.log("balance: "+balance+", check_balance: "+chk_balance);
                console.log(chk_balance);
            }

            final_cash[i] = initial_cash[i] + balance/mem_cnt - contrib[i];
        }

        // all inputs are sanitized
        this.balance = 0;
        for (let i = 0; i < this.size; i++){
            this.active_line[i].visible = true;

            await sleep(this.call_sleep);

            this.balance += contrib_arr[i];
            this.member_cash[i] = icash_arr[i] - contrib_arr[i];
            $(".m_contrib:eq("+i+")").text(':' + Math.round(contrib_arr[i]));
            await this.updateUI (true, true, i);

            this.active_line[i].visible = false;

        }

        await this.updateUI(true);

        for (let i = 0; i < this.size; i++) this.active_line[i].visible = true;

        for (let i = 0; i < this.size; i++) this.member_cash[i] = fcash_arr[i];

        await sleep(this.payoff_isleep);
        await this.updateUI(true, true, this.size, true, 0);
        await sleep(this.payoff_fsleep);

        for (let i = 0; i < this.size; i++) this.active_line[i].visible = false;

        // for (let i in contrib) {
        // if (contrib.hasOwnProperty(i)) {
        // $("#pen"+i+" label" ).text("$" + parseFloat(contrib[i]).toFixed(2));
        // }
        // }
        console.log(this.data);
    };

    this.justice = async function (red_list) {
        // ui function for rewards and punishments

        for (let entry of red_list) {
            punisher_pos = ALL_POSITIONS[parseInt(entry['midx'])];
            punished_pos = ALL_POSITIONS[parseInt(entry['idx'])];
            punisher_cash = parseFloat(entry['cm']);
            punished_cash = parseFloat(entry['ci']);
            punishment_if = entry['rp'];					// if punishment then false, boolean
            console.log(punishment_if);

            // initial graphics rendering
            if (punishment_if) {
                $("#playground div.api div.dynamic div.member_cash:eq("+punisher_pos+")").css({ "color": "#770000"});
                $("#playground div.api div.dynamic div.member_cash:eq("+punished_pos+")").css({ "color": "#ff0000"});
            } else {
                $("#playground div.api div.dynamic div.member_cash:eq("+punisher_pos+")").css({ "color": "#003300"});
                $("#playground div.api div.dynamic div.member_cash:eq("+punished_pos+")").css({ "color": "#007700"});
            }

            //
            // if (punisher_pos < punished_pos) {
            //     var i1 = punisher_pos;
            //     var i2 = punished_pos - punisher_pos - 1;
            // } else {
            //     var i1 = punished_pos;
            //     var i2 = punisher_pos - punished_pos - 1;
            // }
            // if (punishment_if) this.interaction_line_act[i1][i2].visible = true;
            // else this.interaction_line_pos[i1][i2].visible = true;
            //

            this.member_cash[punished_pos] = punished_cash;
            this.member_cash[punisher_pos] = punisher_cash;

            this.updateUI(false, true, punished_pos);
            this.updateUI(false, true, punisher_pos);

            await sleep(this.justice_sleep);


            // final graphics rendering
            $("#playground div.api div.dynamic div.member_cash:eq("+punisher_pos+")").css({ "color": "#000"});
            $("#playground div.api div.dynamic div.member_cash:eq("+punished_pos+")").css({ "color": "#000"});

            // this.interaction_line_act[i1][i2].visible = false;
            // this.interaction_line_pos[i1][i2].visible = false;

        }
    };

    this.render = function(data) {
        if (data !== undefined && this.data.length == 0) this.data = data;

        if (this.render_state == 2) return;

        this.flower_vertex = [];
        this.active_line = [];
        this.inert_line = [];
        this.status_circle = [];
        this.interaction_line = [];
        this.interaction_line_act = [];
        this.interaction_line_pos = [];

        let canvas_pos = $("#playground canvas").position();

        game_canvas_X = canvas_pos.left;
        game_canvas_Y = canvas_pos.top;
        this.center = {x:game_canvas_X + game_canvas_DIM / 2, y: game_canvas_Y + game_canvas_DIM / 2};		// centre of canvas wrt playground
        member_cash_offset = {x: -2, y: -1};
        radius = 0.37 * game_canvas_DIM;
        lstart = 0.05 * game_canvas_DIM;
        lend = 0.31 * game_canvas_DIM;
        uiRadius = 0.48 * game_canvas_DIM;
        hBar_maxlen = 0.05 * game_canvas_DIM;
        vBar_maxlen = 0.05 * game_canvas_DIM;
        uncapped_peep_scale = 0.45 * game_canvas_DIM / 1000;
        capped_peep_scale = 0.8 * game_canvas_DIM / 1000;


        // Sets up the initial display for the society
        if ( this.render_state == 0 ) {
            tournament_peep_t = resources["../publicGoodsGame/assets/images/tournament_peep.json"].textures;
            splash_peep_t = resources["../publicGoodsGame/assets/images/splash_peep.json"].textures;

            // Place the members
            let ii = 0;
            for (let idx in this.data) {
                if (this.data.hasOwnProperty(idx)) {
                    if (!HATS) {
                        this.peep.push(new Sprite(splash_peep_t["splash_peep0000"]));
                        this.peep[ii].anchor.set(0.54, 0.56);
                        this.peep[ii].scale.set(uncapped_peep_scale, uncapped_peep_scale);
                    } else {
                        switch (this.data[idx]['type']) {
                            case 'x':
                                this.peep.push(new Sprite(tournament_peep_t["tournament_peep0007"]));
                                break;
                            case 'b':
                                this.peep.push(new Sprite(tournament_peep_t["tournament_peep0001"]));
                                break;
                            case 'g':
                                this.peep.push(new Sprite(tournament_peep_t["tournament_peep0000"]));
                                break;
                            case 'a':
                                this.peep.push(new Sprite(tournament_peep_t["tournament_peep0003"]));
                                break;
                            case 'c':
                                this.peep.push(new Sprite(tournament_peep_t["tournament_peep0004"]));
                                break;
                            case 'r':
                                this.peep.push(new Sprite(tournament_peep_t["tournament_peep0005"]));
                                break;
                            default:
                                alert("unrecognized prototype");
                                break;
                        }
                        this.peep[ii].anchor.set(0.5,0.6);
                        this.peep[ii].scale.set(capped_peep_scale, capped_peep_scale);
                    }
                    this.peep[ii].position.set(game_canvas_DIM/2 + radius * Math.cos(Math.PI * ii * 2/this.size), game_canvas_DIM/2 - radius * Math.sin(Math.PI * ii * 2/this.size));
                    ii++;
                }
            }
            for (let i = 0; i < this.size; i++) {
                game_canvas.stage.addChild(this.peep[i]);
            }

        } else if (this.render_state == 1) {
            for (let i = 0; i < this.size; i++) {
                this.peep[i].position.set(game_canvas_DIM/2 + radius * Math.cos(Math.PI * i * 2/this.size), game_canvas_DIM/2 - radius * Math.sin(Math.PI * i * 2/this.size));
                this.peep[i].scale.set(uncapped_peep_scale, uncapped_peep_scale);
            }
        }

        // Draw lines
        this.flower_vertex = [];
        for (let i = 0; i < this.size; i++) {

            // Draw connector lines to the centre
            this.inert_line.push(new Graphics());
            this.inert_line[i].lineStyle(2, 0xe0e0e0, 1);
            this.active_line.push(new Graphics());
            this.active_line[i].lineStyle(2, 0xffee00, 1);

            this.inert_line[i].moveTo(game_canvas_DIM/2 + lstart * Math.cos(Math.PI * i * 2/this.size), game_canvas_DIM/2 - lstart * Math.sin(Math.PI * i * 2/this.size));
            this.inert_line[i].lineTo(game_canvas_DIM/2 + lend * Math.cos(Math.PI * i * 2/this.size), game_canvas_DIM/2 - lend * Math.sin(Math.PI * i * 2/this.size));
            this.flower_vertex.push({
                x: game_canvas_DIM/2 + lend * Math.cos(Math.PI * i * 2/this.size),
                y: game_canvas_DIM/2 - lend * Math.sin(Math.PI * i * 2/this.size)
            });
            this.active_line[i].moveTo(game_canvas_DIM/2 + lstart * Math.cos(Math.PI * i * 2/this.size), game_canvas_DIM/2 - lstart * Math.sin(Math.PI * i * 2/this.size));
            this.active_line[i].lineTo(game_canvas_DIM/2 + lend * Math.cos(Math.PI * i * 2/this.size), game_canvas_DIM/2 - lend * Math.sin(Math.PI * i * 2/this.size));
            this.active_line[i].visible = false;
            game_canvas.stage.addChild(this.inert_line[i]);
            game_canvas.stage.addChild(this.active_line[i]);

            // Draw identification circles for human players
            this.status_circle.push(new Graphics());
            this.status_circle[i].beginFill(0x9966FF);
            this.status_circle[i].drawCircle(0, 0, 4);
            this.status_circle[i].endFill();
            this.status_circle[i].x = game_canvas_DIM/2 + lend * Math.cos(Math.PI * i * 2/this.size);
            this.status_circle[i].y = game_canvas_DIM/2 - lend * Math.sin(Math.PI * i * 2/this.size);
            this.status_circle[i].visible = false;
            game_canvas.stage.addChild(this.status_circle[i]);
        }

        // Draw interaction lines: IMPORTANT: interaction_line [i][j] joins peep i to peep i+j+1
        for (let i = 0; i < this.size - 1; i++) {

            this.interaction_line.push([]);
            this.interaction_line_act.push([]);
            this.interaction_line_pos.push([]);

            for (let j = 0; j < this.size - i - 1; j++){

                this.interaction_line[i].push(new Graphics());
                this.interaction_line[i][j].lineStyle(1, 0xe0e0e0, 1);
                this.interaction_line_act[i].push(new Graphics());
                this.interaction_line_act[i][j].lineStyle(1, 0xff0000, 1);
                this.interaction_line_pos[i].push(new Graphics());
                this.interaction_line_pos[i][j].lineStyle(1, 0x007700, 1);

                this.interaction_line[i][j].moveTo(this.flower_vertex[i].x, this.flower_vertex[i].y);
                this.interaction_line[i][j].lineTo(this.flower_vertex[i+j+1].x, this.flower_vertex[i+j+1].y);

                this.interaction_line_act[i][j].moveTo(this.flower_vertex[i].x, this.flower_vertex[i].y);
                this.interaction_line_act[i][j].lineTo(this.flower_vertex[i+j+1].x, this.flower_vertex[i+j+1].y);
                this.interaction_line_act[i][j].visible = false;

                this.interaction_line_pos[i][j].moveTo(this.flower_vertex[i].x, this.flower_vertex[i].y);
                this.interaction_line_pos[i][j].lineTo(this.flower_vertex[i+j+1].x, this.flower_vertex[i+j+1].y);
                this.interaction_line_pos[i][j].visible = false;

                game_canvas.stage.addChild(this.interaction_line[i][j]);
                game_canvas.stage.addChild(this.interaction_line_act[i][j]);
                game_canvas.stage.addChild(this.interaction_line_pos[i][j]);

            }
        }

        let ii = 0;
        for (let idx in this.data) {
            if (this.data.hasOwnProperty(idx)) {

                // mention the cash
                if (this.render_state == 0 ) {
                    $(".dynamic").append("<div class = 'member_cash' style = 'order: " + idx + " ;'><span class='m_cash'></span><span class='m_contrib'></span></div>");
                    this.member_cash[ii] = parseFloat(this.data[idx]['cash']);

                    // idx maintainance
                    ALL_POSITIONS[idx] = ii;
                    ALL_IDXS.push(idx);

                    // Do NOT overwrite cash if already mentioned
                }
                // console.log(this.data[idx]['cash']);
                $(".dynamic .member_cash:eq("+ii+") .m_cash").text("$" + this.data[idx]['cash']);

                let member_cash_ii_object = $(".dynamic .member_cash:eq("+ii+")");

                let memcash_text_width = member_cash_ii_object.width();
                let memcash_text_height = member_cash_ii_object.height();

                let cash_y = (this.center.y - uiRadius * Math.sin(Math.PI * ii * 2/this.size) - memcash_text_height/2 + member_cash_offset.y);
                let cash_x = (this.center.x + uiRadius * Math.cos(Math.PI * ii * 2/this.size) - memcash_text_width/2 + member_cash_offset.x);


                $("#canvas-info").css({
                    "top": (this.center.y - 48) + "px",
                    "left": (this.center.x + 72) + "px"
                });


                if (this.render_state == 0 ) {
                    $("#names").append('<div class="player-name" id="name'+ii+'"> '+ this.data[idx]['name'] +' </div>');

                    $("#canvas-info").show();

                    $("#penalties").append('<div class="pen-input" id="pen'+idx+'"><span class = "target">'+
                        this.data[idx]['name']+'</span><div class="range-slider group"><input class="range-slider__range" type="range" value="0" min="-100" max="0" step="1"><span class="range-slider__value"  id="p'+idx+'">0</span></div></div>')
                }

                member_cash_ii_object.css({
                    "top": cash_y + "px",
                    "left": cash_x + "px"
                });

                ii++;
            }
        }

        rangeSlider();

        // position the names
        for (let i = 0; i < this.size; i++) {
            let pen_offset = {
                "x": -16,
                "y": 6
            };
            let pen_y = (this.center.y - (uiRadius) * Math.sin(Math.PI * i * 2/this.size) + pen_offset.y);
            let pen_x = (this.center.x + (uiRadius) * Math.cos(Math.PI * i * 2/this.size) + pen_offset.x);
            $("#names #name"+i+".player-name").css({
                "top": pen_y + "px",
                "left": pen_x + "px"
            });
        }
        this.updateUI(true, true);
        this.render_state = 2;
    };

    this.wrap_up_partial = async function () {
        // remove just the lines
        for (var i = this.size; i >= 0; i--) {
            game_canvas.stage.removeChild(this.inert_line[i]);
            game_canvas.stage.removeChild(this.active_line[i]);
            game_canvas.stage.removeChild(this.status_circle[i]);
            if (i < this.size - 1) {
                for (let j = this.interaction_line[i].length - 1; j >= 0; j--) game_canvas.stage.removeChild(this.interaction_line[i][j]);
                for (let j = this.interaction_line_act[i].length - 1; j >= 0; j--) game_canvas.stage.removeChild(this.interaction_line_act[i][j]);
                for (let j = this.interaction_line_pos[i].length - 1; j >= 0; j--) game_canvas.stage.removeChild(this.interaction_line_pos[i][j]);
            }
        }
        this.render_state = 1;
    };

    this.wrap_up_full = async function (){
        for (var i = this.size; i >= 0; i--) {
            game_canvas.stage.removeChild(this.peep[i]);
            game_canvas.stage.removeChild(this.inert_line[i]);
            game_canvas.stage.removeChild(this.active_line[i]);
            if (i < this.size - 1) {
                for (let j = this.interaction_line[i].length - 1; j >= 0; j--) game_canvas.stage.removeChild(this.interaction_line[i][j]);
                for (let j = this.interaction_line_act[i].length - 1; j >= 0; j--) game_canvas.stage.removeChild(this.interaction_line_act[i][j]);
                for (let j = this.interaction_line_pos[i].length - 1; j >= 0; j--) game_canvas.stage.removeChild(this.interaction_line_pos[i][j]);
            }
        }
        $(".dynamic").html("");
        $("#penalties").html("");
        $("#balance").html("");
        this.render_state = 0;
    }

}


function renderGame(pop, data) {
    SocReqd = true;
    console.log(JSON.stringify(data));
    if (activeSoc) console.log(activeSoc.render_state);
    if (mainDone && !SocDef) {
        SocDef = true;
        activeSoc = new SocRenderer(pop, data);
        activeSoc.render(data);
    } else {
        console.log("mainDone: " + mainDone );
        pop_mem = pop;
        data_mem = data;
    }
}

/* Initial Setup */
$(function () {
    loader
        .add("../publicGoodsGame/assets/images/splash_peep.png")
        .add("../publicGoodsGame/assets/images/splash_peep.json")
        .add("../publicGoodsGame/assets/images/sandbox_hats.png")
        .add("../publicGoodsGame/assets/images/sandbox_hats.json")
        .add("../publicGoodsGame/assets/images/tournament_peep.png")
        .add("../publicGoodsGame/assets/images/tournament_peep.json")
        .load(main);
});

async function main() {

    control_canvas = new Application ({
        antialias: true,    // default: false
        transparent: true, 	// default: false
        resolution: 1       // default: 1
    });

    control_canvas.renderer.view.style.display = "block";
    control_canvas.renderer.autoResize = true;
    control_canvas.renderer.resize(240, 36);

    $("#hatspace").append(control_canvas.view);

    let hats_t = resources["../publicGoodsGame/assets/images/sandbox_hats.json"].textures;

    var hats = [];
    hats.push(new Sprite(hats_t["peep_hat0001"]));
    hats.push(new Sprite(hats_t["peep_hat0000"]));
    hats.push(new Sprite(hats_t["peep_hat0004"]));
    hats.push(new Sprite(hats_t["peep_hat0003"]));
    hats.push(new Sprite(hats_t["peep_hat0006"]));

    for (let i = 0; i < hats.length; i++) {
        hats[i].anchor.set(0.35,0.6);
        hats[i].position.set(18 + 36 * i, 24);
        hats[i].scale.set(0.36,0.36);
        control_canvas.stage.addChild(hats[i]);
    }

    game_canvas = new Application({
        antialias: true,
        transparent: true,
        resolution: 1
    });

    let playground_obj = $("#playground");

    game_canvas_DIM = Math.min(playground_obj.width(), playground_obj.height()) - (playground_obj.innerWidth() - playground_obj.width()) * 2;
    game_canvas.renderer.view.style.display = "block";
    game_canvas.renderer.autoResize = true;
    game_canvas.renderer.resize(game_canvas_DIM, game_canvas_DIM);


    await playground_obj.append(game_canvas.view);

    mainDone = true;
    if (SocReqd && !SocDef) {
        // in case of refresh or resize
        renderGame(pop_mem, data_mem);
    }

    playground_obj.resize(function () {
        waitForFinalEvent(async function(){
            if (SocDef)	await activeSoc.wrap_up_partial();
            let playground_obj = $("#playground");

            game_canvas_DIM = Math.min(playground_obj.width(), playground_obj.height()) - (playground_obj.innerWidth() - playground_obj.width()) * 2;
            await game_canvas.renderer.resize(game_canvas_DIM, game_canvas_DIM);

            if (SocDef) activeSoc.render();

        }, 250, "some unique string");
    });
    $(window).resize(function () {
        waitForFinalEvent(async function(){
            if (SocDef)	await activeSoc.wrap_up_partial();
            let playground_obj = $("#playground");

            game_canvas_DIM = Math.min(playground_obj.width(), playground_obj.height()) - (playground_obj.innerWidth() - playground_obj.width()) * 2;
            await game_canvas.renderer.resize(game_canvas_DIM, game_canvas_DIM);

            if (SocDef) activeSoc.render();

        }, 250, "some unique string");
    });
}