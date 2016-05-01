<?php
//Credited from http://tutorialzine.com/2010/10/ajax-web-chat-php-mysql/
$dbOptions = array(
	'db_host' => 'waterbase.web.engr.illinois.edu',
	'db_user' => 'waterbas_nit',
	'db_pass' => 'smitak123',
	'db_name' => 'waterbas_nit'
);

error_reporting(E_ALL ^ E_NOTICE);

require "classes/DB.class.php";
require "classes/Chat.class.php";
require "classes/ChatBase.class.php";
require "classes/ChatLine.class.php";
require "classes/ChatUser.class.php";
require "classes/SentimentAnalysis.class.php";

session_name('webchat');
session_start();

if(get_magic_quotes_gpc()){
	
	// If magic quotes is enabled, strip the extra slashes
	array_walk_recursive($_GET,create_function('&$v,$k','$v = stripslashes($v);'));
	array_walk_recursive($_POST,create_function('&$v,$k','$v = stripslashes($v);'));
}

try{
	
	// Connecting to the database
	DB::init($dbOptions);
	
	$response = array();
	
	// Handling the supported actions:
	
	switch($_GET['action']){
		
		case 'signup':
			$response = Chat::signup($_POST['email'], $_POST['name'], $_POST['password'], $_POST['confirmPassword']);
		
		case 'login':
			$response = Chat::login($_POST['email'],$_POST['password']);
			break;
		
		case 'checkLogged':
			$response = Chat::checkLogged();
			break;
		
		case 'logout':
			$response = Chat::logout();
			break;
		
		case 'logoutAndDelete':
			$response = Chat::logoutAndDelete();
			break;
		
		case 'joinRoom':
			$response = Chat::joinRoom($_POST['roomID'], $_POST['password']);
			break;
		
		case 'submitChat':
			$response = Chat::submitChat($_POST['chatText']);
			break;
		
		case 'submitRoom':
			$response = Chat::submitRoom($_POST['name'], $_POST['password']);
			break;
		
		case 'getUsers':
			$response = Chat::getUsers();
			break;
		
		case 'getChatHistory':
			$response = Chat::getChatHistory();
			break;
		
		case 'getMessages':
			$response = Chat::getMessages($_GET['lastMsgId']);
			break;
		
		case 'getRooms':
			$response = Chat::getRooms();
			break;
		
		case 'search':
			$response = Chat::search($_POST['filterText']);
			break;

		case 'updateUsername':
			$response = Chat::updateUsername($_POST['username']);
			break;
		
		case 'getUserRelationships':
			$response = Chat::getUserRelationships();
			break;
		
		default:
			throw new Exception('Wrong action');
	}
	
	echo json_encode($response);
}
catch(Exception $e){
	die(json_encode(array('error' => $e->getMessage())));
}

?>