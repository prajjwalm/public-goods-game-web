/* global variables */
var RUN = false;
var POP_CNTS = [4, 5, 5, 3, 2];
var HUMAN = 0;
var PUNISH = false;
var HATS = true;
var REF = 100;
var _pop_refresh_reqd = false;
var warning_ongoing = false;
var info_ongoing = false;

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
	if (info_ongoing) {
		await sleep(1500);
	}
	$("#warning span").html(str);
	warning_ongoing = true;
	$("#warning").fadeIn(function () {
		$("#warning").delay(1500).fadeOut(function () {
			warning_ongoing = false;
		});
	});
}

async function inform(str) {
	if (warning_ongoing) {
		await sleep(1500);
	}
	$("#info span").html(str);
	info_ongoing = true;
	
	$("#info").fadeIn(function () {
		$("#info").delay(1500).fadeOut(function () {
			warning_ongoing = false;
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