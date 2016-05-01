<?php

/* The Chat class exploses public static methods, used by ajax.php */

class Chat{
	
	public static function login($email, $password){
			   
		if(!$email || !$password){
			throw new Exception('Fill in all the required fields.');
		}
		
		if(!filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL)){
			throw new Exception('Your email is invalid.');
		}
		
		// Users inactive for more than a minute are logged out
		DB::query("UPDATE Users 
			   SET logged_in = 0 
			   WHERE last_activity < SUBTIME(NOW(),'0:00:30')");
			   
		$userInfo = DB::query("SELECT * FROM Users WHERE email = '".$email."'")->fetch_object();
		if($userInfo->password != $password) throw new Exception('Wrong password.');
		if($userInfo->logged_in == 1)throw new Exception('Already logged on. Wait for the system to log out out automatically (30 seconds).');
		
		DB::query("UPDATE Users 
	  		   SET logged_in = 1 
	   		   WHERE user_id ='".$_SESSION['user']['id']."'");
		/*
		if($user->save()->affected_rows != 1){
			throw new Exception('This user is already logged on.');
		}*/
		
		$_SESSION['user'] = array(
			'name'		=> $userInfo->name,
			'id'		=> $userInfo->user_id
		);
		
		return array(
			'status'	=> 1,
			'name'		=> $userInfo->name,
			'id'		=> $id
		);
	}
	
	public static function signup($email, $name, $password, $confirmPassword){
		if ($password != $confirmPassword) throw new Exception("Passwords don't match");
		if(mysqli_num_rows(DB::query("Select * FROM Users WHERE email='".$email."'")) != 0) throw new Exception("User with that email already exists");
		if(mysqli_num_rows(DB::query("Select * FROM Users WHERE name='".$name."'")) != 0) throw new Exception("User with that nickname already exists");
		DB::query("INSERT INTO Users (name, password, email) 
			   VALUES( 
			   '".$name."', 
			   '".$password."', 
			   '".$email."' 
			   )");
	}
	
	public static function checkLogged(){
		$response = array('logged' => false);
			
		if($_SESSION['user']['name']){
			$response['logged'] = true;
			$response['name'] = $_SESSION['user']['name'];
			if($_SESSION['room'])$response['roomName'] = $_SESSION['room']['name'];
		}
		
		return $response;
	}
	
	public static function logout(){
		DB::query("UPDATE Users 
			   SET room_id = NULL, logged_in = 0 
			   WHERE user_id ='".$_SESSION['user']['id']."'");
		
		$_SESSION = array();
		unset($_SESSION);

		return array('status' => 1);
	}
	
	public static function logoutAndDelete(){
		DB::query("DELETE FROM Messages 
			   WHERE author = '".DB::esc($_SESSION['user']['name'])."'");
		DB::query("DELETE FROM Users 
			   WHERE name = '".DB::esc($_SESSION['user']['name'])."'");

		$_SESSION = array();
		unset($_SESSION);
		
		$result= DB::messageQuery('SELECT * FROM Messages');
		return array('chats' => $result);
	}
	
	public static function submitChat($chatText){
		if(!$_SESSION['user']){
			throw new Exception('You are not logged in');
		}
		
		if(!$chatText){
			throw new Exception('You haven\'t entered a chat message.');
		}
		
		if(!$_SESSION['room']){
			throw new Exception('You haven\'t joined a room.');
		}

		
		
		$sentiment = SentimentAnalysis::getSentimentScore($chatText);
		$message = array(
			'user_id'	=> $_SESSION['user']['id'],
			'text'		=> $chatText,
			'room_id'	=> $_SESSION['room']['id'],
			'sentiment'	=> $sentiment
		);
		
		// Get all users in the same room who received the message
		$recipients = DB::messageQuery("SELECT user_id FROM Users
						WHERE logged_in = 1 
						AND user_id != ".$_SESSION['user']['id']." 
						AND room_id = ".$_SESSION['room']['id']);
		
		// Add message to database
		$chat = new ChatLine($message);
		$insert_id = $chat->save()->insert_id;	
		
		// Track message recipients
		foreach($recipients as $user)
		{
			DB::query("INSERT INTO MessageReceived  (from_user_id, to_user_id, message_id) 
				   VALUES (".$_SESSION['user']['id'].", ".$user->user_id.", ".$insert_id.")");
		}	
			
		
		// Check if message requires a bot response
		$check_bot = substr($chatText, 0, 4); // Checking for check_bot = "bot "
		if($check_bot == "bot ") {
			$bot_input = substr($chatText, 4);
			
			$check_whatis = substr($bot_input, 0, 8);
			if($check_whatis == "what is ") {
				$whatis_input = substr($bot_input, 8);
				$command = 'export PYTHONPATH=/home/waterbase/local/lib/python2.6/site-packages/ && /usr/bin/python /home/waterbase/public_html/php/classes/botcrawler.py "'.$whatis_input.'"';
				$pid = popen($command, 'r');
				
				$bot_reply = "";
				
				while( !feof( $pid ) )
				{
					$temp = fread($pid, 256);
					$bot_reply = $bot_reply.$temp;
					usleep(100000);
				}
				pclose($pid);
			}
			
			// Else regular response from bot
			else {
				// Bot output is message_id+1
				$db_msgID = DB::query("SELECT message_id 
							FROM Messages
							WHERE UPPER(text) = UPPER('".$bot_input."')
					      		");
				
				// Randomized bot reply section
				$num_row = $db_msgID->num_rows;
				$row = intval($num_row);
				$range = rand(1, $row);
				
				
				while($range > 0) {
					$selected_msgID = $db_msgID->fetch_assoc();
					$range--;
				}
				$msgID = $selected_msgID['message_id'];
				
				$bot_output = DB::query("SELECT * 
							FROM Messages
							WHERE message_id = 1 + '".$msgID."'
					      		");
				
				$bot_array = $bot_output->fetch_assoc();
				if ($bot_array['text'] == ""){
					$bot_array['text'] = "Sorry, I have not learnt that yet! Keep talking more so that I can learn more";
				}
				$bot_reply = $bot_array['text'];
			}
			
			// Add bot reply to all users and chat screen
			$sentiment2 = SentimentAnalysis::getSentimentScore($bot_reply);
			$message2 = array(
				//bot user_id is 0
				'user_id'	=> 0,
				'text'		=> $bot_reply,
				'room_id'	=> $_SESSION['room']['id'],
				'sentiment'	=> $sentiment2
			);
			
			// Add message to database
			$chat2 = new ChatLine($message2);
			$insert_id2 = $chat2->save()->insert_id;
			
			// Track message recipients
			foreach($recipients as $user)
			{
				DB::query("INSERT INTO MessageReceived  (from_user_id, to_user_id, message_id) 
					   VALUES (".$_SESSION['user']['id'].", ".$user->user_id.", ".$insert_id2.")");
			}
			
		}

		return array();
	}
	
	public static function getUserRelationships()
	{
		$result = DB::query("SELECT Users1.name as name1, AVG(sentiment) as average_sentiment, Users2.name as name2 
				     FROM Users as Users1, Messages, Users as Users2, MessageReceived
				     WHERE Users1.user_id = MessageReceived.from_user_id 
				     AND Users2.user_id = MessageReceived.to_user_id
				     AND Messages.message_id = MessageReceived.message_id
				     GROUP BY Users1.name, Users2.name"); 
		$messages = array();
		while($row = $result->fetch_object())
		{
			$messages[] = $row;
		}
		return $messages;
	}
	
	public static function getChatHistory()
	{
		$result = DB::messageQuery("SELECT sentiment, ts from Messages WHERE user_id=".$_SESSION['user']['id']);
		
		return $result;
	}
			
	public static function submitRoom($name, $password){
		if(strlen($name) < 3)throw new Exception('Room name must be at least 3 characters long');
		
		$exists = DB::query("SELECT * FROM Room
				     WHERE name = '".$name."'");
		if(mysqli_num_rows($exists) > 0) {
			return array('error' => 'Room with that name already exists');
		}
				     
		DB::query("INSERT INTO Room (name, password, creator_id)
			   VALUES(
			   '".$name."',
			   '".$password."',
			   ".$_SESSION['user']['id']."
			   )");
		return array();
	}
	
	public static function joinRoom($roomID, $password)
	{
		if(!$_SESSION['user']) throw new Exception('You must be logged in to join a chat room');
		
		$room = DB::query('SELECT * 
				   FROM Room 
				   WHERE room_id = '.$roomID)->fetch_object();
		
		if($room->password != '' && $password != $room->password) throw new Exception('Wrong password');
		
		$_SESSION['room']['id'] = $roomID;
		
		$messages = DB::messageQuery("SELECT name, text, message_id, ts , sentiment 
						FROM Messages, Users 
						WHERE Messages.user_id = Users.user_id 
						AND Messages.room_id =".$roomID." 
						ORDER BY message_id ASC");
		
		DB::query("UPDATE Users 
			   SET room_id='".$roomID."' 
			   WHERE name ='".$_SESSION['user']['name']."'");
			   
		$_SESSION['room']['id'] = $roomID;
		$_SESSION['room']['name'] = $room->name;
		
		return array('status'	=> 1,
			     'name'	=> $_SESSION['user']['name'],
			     'roomName'	=> $room->name,
			     'messages' => $messages);
	}
	
	public static function getRooms(){
		$result = DB::query("Select name, room_id, creator_id,
				     (CASE WHEN password = '' THEN 0 ELSE 1 END) as locked  
				     FROM Room");
		$rooms = array();
		while($room = $result->fetch_object()){
			$room->locked = $room->locked == "1" ? true : false;
			$room->creator = ($_SESSION['user']['id'] && $room->creator_id == $_SESSION['user']['id']) ? true : false;
			$rooms[] = $room;
			
		}
		return array('rooms' => $rooms);
	}
	
	public static function getUsers(){
		if($_SESSION['user']['name']){
			$user = new ChatUser(array('name' => $_SESSION['user']['name']));
			$user->update();
			
			if(mysqli_num_rows(DB::query("Select * FROM Users WHERE user_id='".$_SESSION['user']['id']."' AND logged_in = 1")) == 0) {
				DB::query("UPDATE Users 
			  		   SET logged_in = 1 
			   		   WHERE user_id ='".$_SESSION['user']['id']."'");
			}
		}
		
		if(!$_SESSION['room']['id']) {
			return array('noRoom'=> true);
		}
			   
		// Users inactive for more than a minute are logged out
		DB::query("UPDATE Users 
			   SET logged_in = 0 
			   WHERE last_activity < SUBTIME(NOW(),'0:00:30')");
		
		$result = DB::query("SELECT * FROM Users 
				     WHERE logged_in = 1 
				     AND room_id =".$_SESSION['room']['id']." 
				     ORDER BY name ASC LIMIT 18");
		
		$users = array();
		while($user = $result->fetch_object()){
			$users[] = $user;
		}
	
		return array(
			'users' => $users,
			'total' => DB::query("SELECT COUNT(*) as count FROM Users 
					      WHERE logged_in = 1 
					      AND room_id = ".$_SESSION['room']['id'])->fetch_object()->count
		);
	}
	
	public static function updateUsername($username){
		if($_SESSION['user']['name'] && mysqli_num_rows(DB::query('SELECT * FROM Users 
									   WHERE name = \''.$username.'\'')) == 0){
			$olduser = $_SESSION['user']['name'];
			$_SESSION['user']['name'] = $username;
			
			DB::query("UPDATE Users 
				   SET name = '".$username."' 
				   WHERE name = '" .DB::esc($olduser)."'");

		} else {
			return array('error' => 'Username invalid or already taken');
		}
		
	}
	
	public static function getMessages($lastMsgID){
		$lastMsgID = (int)$lastMsgID;
		$result = DB::messageQuery('	SELECT name,text,message_id,ts,sentiment 
						FROM Messages, Users
						WHERE Messages.user_id = Users.user_id
						AND message_id > '.$lastMsgID.' AND Messages.room_id ='.$_SESSION['room']['id'].' 
						ORDER BY message_id ASC');
		return array('messages' => $result);
	}
	
	public static function search($filterText){
		$filterText = (string)$filterText;
		$result= DB::messageQuery('	SELECT name,text,message_id,ts,sentiment 
						FROM Messages, Users
						WHERE Messages.user_id = Users.user_id 
						AND text LIKE \'%'.$filterText.'%\' AND Messages.room_id ='.$_SESSION['room']['id'].' 
						ORDER BY message_id ASC');
		return array('messages' => $result);
	}
}


?>