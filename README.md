#THE BATTLE OF THE KINGS

##A. The Game

a.There is a board game played between two players. The board has nXn equal 
squares, where 8 <= n <= 15  (similar to a chess board). 

b. Only two kings will be placed on the board at the starting of the game (one at 1,1 
and second at n,n position). 

c. The kings can move one square in any direction - up, down, to the sides, and 
diagonally, just like chess. 

d. Each square can be visited at-most once by either of the kings.

e. The king who gets killed, or is out of moves loses the game.

##B. What you need to do?

a. You need to write a bot, which can play the game on your behalf. And expose this bot as a webservice.

b. Your webservice should run on port 8080 and expose three APIs:

URL Type Parameter Sample Response Purpose 

````
/ping GET -- {ok: true} 
To let us know that your bot server is alive 
````
````
/start GET y,o,g {ok:true} For you to initialize the round. Here y 
````
````
/play GET m {m:”1|2”} m : Move made by your opponent.
````

Note: By convention all positions are represented as x|y where x and y are respective 
coordinates on the board. Bottom left board is 1|1.


##Requirements:

1. Composer
2. Redis


## Our Algorithm

The bot plays defensive and make sure its next move will not lead to it's death or to deadend. The algorithm is a greedy approach and focuses on generating moves to continue moving or wandering on board as much as possible without violating any rule and waiting for our opponent to make a mistake of coming in range so that it can be knocked out or waiting for opponent to run out of possible steps.It is a simple algorithm that increases chances of winning in most cases but not in every case.It works as

1. Calculates valid steps out of 8 possible steps in the neighbourhood and returns valid locations.

2. From each valid step calculated above maximum distance is calculated and nodes are sorted in descending order.The node with maximum distance in clockwise direction is taken as next move.   

##Authors:

1. [Prashant Chaudhary @pc9](http://github.com/pc9)

2. [Nikhil Srivastava @niksrc](http://github.com/niksrc)

3. [Anant Garg @infinitegarg](http://github.com/infinitegarg)


##License

MIT License