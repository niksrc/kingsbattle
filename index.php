<?php

require 'vendor/autoload.php';

$app = new \Slim\Slim();
$client = new Predis\Client();

$app->response->headers->set('Content-Type', 'application/json');

//This endpoint describes whether the server is up or not.In case it is disconnect from internet the game is cancelled.

$app->get('/ping',function(){
	echo json_encode(["ok"=>true]);
});

/**The start endpoint initialize the game requirements,initial positions and grid size.
* The input format of positions is  x|y and hence need to be converted into (x,y).
* 0 describes a position being empty i.e, it is not filled currently or in the past.
* 1 describes a postion being filled i.e, it is filled currently or in the past. 
**/

$app->get('/start',function() use ($app,$client){
	$ourKingPos = $app->request->get("y");
	$opponentKingPos = $app->request->get("o");
	$gridSize = $app->request->get("g");
	
	if(!$ourKingPos || !$opponentKingPos || !$gridSize)
		echo json_encode(["ok"=>false,"msg"=>"invalid parameters"]);
	else{
		$client->set('initData',json_encode(['ourKingPos'=>$ourKingPos,'opponentKingPos'=>$opponentKingPos,'gridSize'=>$gridSize]));
		for($i = 0;$i <$gridSize;$i++)
			for($j = 0;$j <$gridSize;$j++)
				$board[$i][$j] = 0;
		
		$ourKP = explode('|', $ourKingPos);
		$opponentKP = explode('|', $opponentKingPos);
		
		$board[$ourKP[0]-1][$ourKP[1]-1] = 1;
		$board[$opponentKP[0]-1][$opponentKP[1]-1] = 1;

		//save initial data to redis
		$client->set('boardData',json_encode($board));
	
		echo json_encode(["ok"=>true]);
	}
});



/**
* This function evaluates the 8 nearby coordinates to a point and tells whether they are safe or  * not for move. (-1,-1) describes a unsafe position i.e, it is either filled currently or in the  * past or out of bounds. It returns an array with all the postions marked safe or not.
**/
function getNearByCoordinates($x,$y,$gridSize,$board){
	$arr = array(
		'top'=>[-1,-1],
		'topRight'=>[-1,-1],
		'right'=>[-1,-1],
		'rightBottom'=>[-1,-1],
		'bottom'=>[-1,-1],
		'bottomLeft'=>[-1,-1],
		'left'=>[-1,-1],
		'leftTop'=>[-1,-1]
	);
	
	if( ($y+1)<$gridSize && $board[$x][$y+1] != 1)
		$arr['top'] = [$x,$y+1];
	
	if( ($y+1)<$gridSize && ($x+1)<$gridSize && $board[$x+1][$y+1] != 1)
		$arr['topRight'] = [$x+1,$y+1];
	
	if( ($x+1)<$gridSize && $board[$x+1][$y] != 1)
		$arr['right'] = [$x+1,$y];
	
	if(($x+1)<$gridSize && ($y-1)>=0 && $board[$x+1][$y-1] != 1)
		$arr['rightBottom'] = [$x+1,$y-1];
	
	if( ($y-1)>=0 && $board[$x][$y-1] != 1)
		$arr['bottom'] = [$x,$y-1];
	
	if(($y-1)>=0 && ($x-1)>=0 && $board[$x-1][$y-1] != 1)
		$arr['bottomLeft'] = [$x-1,$y-1];
	
	if(($x-1)>=0 && $board[$x-1][$y] != 1)
		$arr['left'] = [$x-1,$y];
	
	if(($y+1)<$gridSize && ($x-1)>=0 && $board[$x-1][$y+1] != 1)
		$arr['leftTop'] = [$x-1,$y+1];
	
	return $arr;
}

/**
* This function gives the distance to the farthest safe position in a given direction from a given 
* point.
**/
function distance($x,$y,$type,$gridSize,$board){
	$sum = 0;
	
	if( $type == 'top')
		for($i=$y;$i<$gridSize && $board[$x][$i]!=1;$i++)
			$sum ++;
	if( $type == 'topRight')
		for($i=$x,$j=$y;$i<$gridSize && $j<$gridSize && $board[$i][$j]!=1;$i++,$j++)
			$sum ++;

	if( $type == 'right')
		for($i=$x;$i<$gridSize && $board[$i][$y]!=1;$i++)
			$sum ++;

	if( $type == 'rightBottom')
		for($i=$x,$j=$y;$i<$gridSize && $j>=0 && $board[$i][$j]!=1;$i++,$j--)
			$sum ++;

	if( $type == 'bottom')
		for($i=$y;$i>=0 && $board[$x][$i]!=1;$i--)
			$sum ++;

	if( $type == 'bottomLeft')
		for($i=$x,$j=$y;$i>=0 && $j>=0 && $board[$i][$j]!=1;$i--,$j--)
			$sum ++;

	if( $type == 'left')
		for($i=$x;$i>=0 && $board[$i][$y]!=1;$i--)
			$sum ++;

	if( $type == 'leftTop')
		for($i=$x,$j=$y;$i>=0 && $j<$gridSize && $board[$i][$j]!=1;$i--,$j++)
			$sum ++;

	return $sum;
}

/**
* Play end point gives move corresponding to a opponents move
* The main logic is to calculate the safe distance in all directions from current position and     * move in a direction with longest length. 
**/
$app->get('/play',function() use($app,$client){
	$move = $app->request->get('m');
	
	if(!!$move){
		$opponentKingPos = $move;
	
		$move = explode('|', $move);
	
		$board = json_decode($client->get('boardData'));
		$initData = json_decode($client->get('initData'),true);
	
		$ourKingPos = explode('|',$initData['ourKingPos']);
	
		//Reflect the oppenent's move in in the board
		$board[$move[0]-1][$move[1]-1] = 1;
	
		//Near by safe coordinates to our position
		$arr = getNearByCoordinates($ourKingPos[0]-1,$ourKingPos[1]-1,$initData['gridSize'],$board);
		
		//Near by safe coordinates to oppponent position
		$arr2 = getNearByCoordinates($move[0]-1,$move[1]-1,$initData['gridSize'],$board);
		
		$maxDistance = 0;

		//If oppenent is next to our position,move over it and the game ends

		$x = $ourKingPos[0]-1;$y = $ourKingPos[1]-1;$ox = $move[0]-1;$oy = $move[1]-1;
		
		if(
			($x == $ox && ($y+1)==$oy) || 
			(($x+1) == $ox && ($y+1) == $oy) || 
			(($x+1) == $ox && $y ==$oy) || 
			(($x+1) == $ox && ($y-1)==$oy) || 
			($x == $ox && ($y-1) == $oy) || 
			(($x-1) == $ox && ($y-1)==$oy) || 
			(($x-1) == $ox && $y == $oy) || 
			(($x-1) == $ox && ($y+1) == $oy)
		){
			$nextX = $move[0]-1;
			$nextY = $move[1]-1;
		}
		else
		{
			foreach ($arr as $key => $value) {
				if($value[0] != -1){
					if(($x = distance($value[0],$value[1],$key,$initData['gridSize'],$board)) > $maxDistance ){
						$maxDistance = $x;
						$nextX = $value[0];
						$nextY = $value[1];
						if(in_array([$nextX,$nextY],$arr2))
							$maxDistance = 0;
					}	
				}
			}
		}
		
		$board[$nextX][$nextY] = 1;
		$ourKingPos = ($nextX+1).'|'.($nextY+1);
		$opponentKingPos = $move[0].'|'.$move[1];

		//save moves and grid in redis
		$client->set('initData',json_encode(['ourKingPos'=>$ourKingPos,'opponentKingPos'=>$opponentKingPos,'gridSize'=>$initData['gridSize']]));

		$client->set('boardData',json_encode($board));
		
		echo json_encode(["m"=>"".$ourKingPos]);	
	}
	else
		echo json_encode(["msg"=>"invalid parameters"]);	
	
});

$app->run();
