# Public Goods Game

This web project is my effort in simulating the [public goods game](https://en.wikipedia.org/wiki/Public_goods_game). 
When complete, I hope that this project will offer insight into human behavior in matters of 
cooperation and trust. And how our personalities and societal factors can lead to progress or
 stagnation (or decline).

## The Main Experiment
__Work done__:  A website has been built where multiple users can play variants of the game with each other, where each variant represents some type of society in existence. The following variants have been developed (bold versions are used for carrying out experiments, to compare societies)
* ___Base___: only contributions, standard version of the game
* _Z_:    rewards and punishments enabled, identity of the executor shown
* _X_:    only punishments enabled, identity of the executor hidden
* ___A___:    X with only punishments of small magnitude enabled
* ___B___:    X with only punishments of large magnitude enabled

__Limitation(s)__: The implementation is not yet sturdy enough to handle fluctuations in connectivity, a small temporary lack of connectivity may cause the player to be chucked out of the game, and although uncommon sometimes causes the game to freeze for the other players. Will fix in a later update.

## The Prototype
__Work Done__: Several automated simple algorithm driven bots have been developed. These are used to explore the outcome of different versions of the game with different populations distributions and different multiplicative/penalty factors. The contribution algorithms developed (the bold ones are also available in the main website) are-
 * ___Basic-contributors___: Start with and try to maintain high levels of contribution, are influenced by contribution/cash ratios of other players
 * ___Basic-defectors___: Start with low levels of contribution, are influenced by the contribution/cash ratio of the richest player
 * _Accountants_: Start with medium levels of contribution, keep contribution just below extrapolated (from the last two rounds) average value
 * _Cold-defectors_: Keep contribution to the minimum, always, this is supplemented by the fact that they frequently punish in revenge
 * _Rationals_: Generally keep moderately low levels of contribution, occasionally contribute zero or a very high value.

__Limitations(s)__: These bots have been developed mainly by observing various styles of human gameplay and lack theoretical backing for their algorithms, and so these have mainly been used as prototypes. If time permits, I will develop them more seriously.


## The Theoretical Analysis

This section is part of my repository public-goods-game-res.