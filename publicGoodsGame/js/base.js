//////////////////////////////////////////////////////////////////////////////////////////////// GLOBAL SPACE //////////////////

var POP_CNTS = [0,0,0,0,0];
var PUNISH = false;
var HATS = false;
var REF = 100;
var warning_rnd = false;
var info_rnd = false;
var GAMEON = false;

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/* Trivial Support Functions */
function print(str){
	// appends html to stdout (remember <br /> rather than \n)
	$("#stdout").append(str);
}

function sleep(ms) {
	// call in an async function as: await sleep(50);
	return new Promise(resolve => setTimeout(resolve, ms));
}

async function warn(str) {
	if (info_rnd) {
		await sleep(1500);
	}
	$("#warning span").html(str);
	warning_rnd = true;
	$("#warning").fadeIn(function () {
		$("#warning").delay(1500).fadeOut(function () {
			warning_rnd = false;
		});
	});
}

async function inform(str) {
	if (warning_rnd) {
		await sleep(1500);
	}
	$("#info span").html(str);
	info_rnd = true;
	
	$("#info").fadeIn(function () {
		$("#info").delay(1500).fadeOut(function () {
			warning_rnd = false;
		});
	});
}

function bound(min, val, max) {
	// call as val = bound(min, val, max);
	if (val < min) return min;
	else if (val > max) return max;
	else return val;
}

function shuffle(array) {
	// https://bost.ocks.org/mike/shuffle/
  var currentIndex = array.length, temporaryValue, randomIndex;

  // While there remain elements to shuffle...
  while (0 !== currentIndex) {

    // Pick a remaining element...
    randomIndex = Math.floor(Math.random() * currentIndex);
    currentIndex -= 1;

    // And swap it with the current element.
    temporaryValue = array[currentIndex];
    array[currentIndex] = array[randomIndex];
    array[randomIndex] = temporaryValue;
  }

  return array;
}

// function accomodating callback used for viewport resize on mp_renderer.js ln 13

// String includes function for IE
if (!String.prototype.includes) {
  String.prototype.includes = function(search, start) {
    'use strict';
    if (typeof start !== 'number') {
      start = 0;
    }

    if (start + search.length > this.length) {
      return false;
    } else {
      return this.indexOf(search, start) !== -1;
    }
  };
}

approxeq = function(v1, v2, epsilon) {
  if (epsilon == null) epsilon = 0.002;
  return Math.abs(v1 - v2) < epsilon;
};
