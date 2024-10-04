<?php

// This is a very simple implementation of your own personal cloud
// command processer for Cloud Download / Drive Download and Video Kiosk
//
// It supports the following commands
//
// • login - username & password = token
// • refresh_token - token = token
// • list_paths
// • list_all_paths - token = json encoded array of path info
// • list_folder_paths - token, folder = json encoded array of path info
// • get_folder_info - token, folder = json encoded folder path info

// For demonstation purposes there is a single hardcoded user
// and the access token is stored in a local file
//
// A general purpose implementation could use a database to store user 
// names, passwords, and tokens

// This is the path to the file that will store the token
// Make sure this file is not in your web root so that it
// cannot be accessed by web browsers 
$PATH_TO_TOKENS = "/home/burningthumb/personalcloud/tokens/";

// This is the folder that is the root of your personal cloud
// This folder can be anywhere on your site. If it is in the
// web root the files will be visible to any web brower so
// if you are using private files its best to place this
// folder somewhere that is outside of the web root
$PATH_TO_ROOT_FOLDER = "/home/burningthumb/personalcloud/demo";

// For demonstration purposes, the username is hardcoded
$STORED_USERNAME = "demo";

// For demonstration purposes, the password is hardcoded
// This is the SHA512 encoding of: PersonalCloud1234
$STORED_PASSWORD = "cac75ece8e6d7fa084661d2d3fdd1df1b836252c0f3f76343dbdcc3cab24f10cee930de60ad9e164a4179f2f8423d484b81d1bef2024aac86f683e50f2590f4d";


$ROOT_FORDER_INFO = new SplFileInfo($PATH_TO_ROOT_FOLDER);
$ROOT_FOLDER_PATH = $ROOT_FORDER_INFO->getRealPath();

// The data is expected as JSON so it needs to be read and parsed
$data = json_decode(file_get_contents('php://input'), true);

$mCmd = $data['cmd'];
$mToken = $data['token'];
$mUsername = $data['username'];
$mPassword = $data['password'];
$mPath = $data['path'];

// For testing purposes the data may come in in $_GET variables
if (!isset($mCmd) || (0 == strlen($mCmd)))
{
	$mCmd = $_REQUEST['cmd'];
	$mToken = $_REQUEST['token'];
	$mUsername = $_REQUEST['username'];
	$mPassword = $_REQUEST['password'];
	$mPath = $_REQUEST['path'];
}


$documentRootFolderInfo = new SplFileInfo($_SERVER['DOCUMENT_ROOT']);
$rootFolderInfo = new SplFileInfo($PATH_TO_ROOT_FOLDER);

if (!isset($mPath))
{    
	$mFullPath = $PATH_TO_ROOT_FOLDER;	
}
else
{
	$mFullPath = $rootFolderInfo->getRealPath() . $mPath;
}

$mPathInfo = new SplFileInfo($mFullPath);

switch ($mCmd)
{
	case 'login';
		login($mUsername, $mPassword);
		break;

	case 'refresh_token';
		$STORED_TOKEN = file_get_contents($PATH_TO_TOKENS . $mUsername . bin2hex($mToken));
		refreshToken($mUsername);
		break;

	case 'list_paths';
	case 'list_all_paths';
		$STORED_TOKEN = file_get_contents($PATH_TO_TOKENS . $mUsername . bin2hex($mToken));
		listAllPaths($mFullPath);
		break;

	case 'list_folder_paths';
		$STORED_TOKEN = file_get_contents($PATH_TO_TOKENS . $mUsername  . bin2hex($mToken));
		listFolderPaths($mFullPath);
		break;

	case 'get_folder_info';
		$STORED_TOKEN = file_get_contents($PATH_TO_TOKENS . $mUsername . bin2hex($mToken));
	 	$results = [];

               	$l_key = str_replace($ROOT_FOLDER_PATH ,'',$mFullPath);
               	$l_results[$l_key] = oneFileInfo($mPathInfo);
		echo(json_encode($l_results));
		break;	
	case 'get_file';
		$STORED_TOKEN = file_get_contents($PATH_TO_TOKENS . $mUsername . bin2hex($mToken));
		getFile($mPathInfo);
		break;

	default;
		http_response_code(203);
                die("Invalid or Missing Command: $mCmd");
}

function login($a_username, $a_password)
{
	global $STORED_USERNAME;
	global $STORED_PASSWORD;

	if ((!isset($a_username)) || (0 == strlen($a_username)))
	{
		http_response_code(203);
		die("Missing Username");
	}

	// Password is sent as a SHA512 so this should never, ever happen
	// A check for a SHA512 for an empty string could be added if you want to return this
	// error
	if ((!isset($a_password)) || (0 == strlen($a_password)))
        {
                http_response_code(203);
                die("Missing Password");
	}

	if ((!($STORED_USERNAME == $a_username)) )
	{
		http_response_code(203);
		die("Invalid Username or Password");
	}

	if ((!($STORED_PASSWORD == $a_password)))
	{
		http_response_code(203);
		die("Invalid Username or Password");
	}

	setAndReturnNewToken($a_username);

}

function validateToken()
{
	global $mToken;
	global $STORED_TOKEN;

	if ((!isset($mToken)) || (0 == strlen($mToken)))
	{
		http_response_code(203);	
		die("Missing Token");
	}

	if (0 == strlen($STORED_TOKEN))
	{
		http_response_code(203);
                die("No Stored Token");
	}

	if (!($mToken == $STORED_TOKEN))
	{
		http_response_code(203);
                die("Token Mismatch");
	}
}

function listFolderPaths($a_folder)
{
	validateToken();

	$l_files = new DirectoryIterator($a_folder);
	
	returnFolderResults($l_files);

}

function returnFolderResults($a_files)
{
	global $ROOT_FOLDER_PATH;

	$l_results = [];

	foreach($a_files as $l_file)
	{
		if ($l_file->isDot()) continue;

               	$l_key = str_replace($ROOT_FOLDER_PATH ,'',$l_file->getRealPath());
		$l_info = oneFileInfo($l_file);

		$l_results[$l_key] = $l_info;
	}

	echo(json_encode($l_results));
}

function listAllPaths($a_folder)
{
	validateToken();

	$l_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $a_folder, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
	
	returnAllResults($l_files);

}

function returnAllResults($a_files)
{
	global $ROOT_FOLDER_PATH;

	$l_results = [];

	foreach($a_files as $l_file)
	{
               	$l_key = str_replace($ROOT_FOLDER_PATH ,'',$l_file->getRealPath());
		
		$l_info = oneFileInfo($l_file);

		$l_results[$l_key] = $l_info;
	}

	echo(json_encode($l_results));
}

function oneFileInfo($a_file)
{
	$l_info = [];

	// Basic file information that the parser will look for
        $l_info["ctime"] = $a_file->getCTime();
        $l_info["length"] = $a_file->getSize();
	$l_info["isdir"] = $a_file->isDir();

	// This is currently not used but you do need to return it since
	// the parser is looking for it
	$l_info["etag"] = $l_info["ctime"] . $l_info["length"];

	// Here you can return any remote id that you want
	// This script returns a URL to files accessible in the
	// webroot but you could easily change it to a URL
	// to a script that returns files that are not in the
	// webroot
	//
	// It must be a valid URL since the client will POST to this
	// URL and expect the file in response
	$l_info["remoteid"] = pathUrl($a_file->getRealPath());
	
	return $l_info;
}

function refreshToken($a_username)
{
	global $STORED_TOKEN;
	global $mToken;
	
	if ((0 == strlen($STORED_TOKEN)) || ($mToken == $STORED_TOKEN))
	{
		setAndReturnNewToken($a_username);
	}
	else
	{
		http_response_code(203);
		die("Forbidden");
	}
}

function setAndReturnNewToken($a_username)
{
	global $PATH_TO_TOKENS;

	$l_date = date('m/d/Y h:i:s a', time());
        $l_hash = password_hash($a_username . $l_date, PASSWORD_BCRYPT);
        file_put_contents($PATH_TO_TOKENS . $a_username . bin2hex($l_hash), $l_hash);
        echo($l_hash);
}

function pathUrl($dir = __DIR__){

    $root = "";
    $dir = str_replace('\\', '/', realpath($dir));

    //HTTPS or HTTP
    $root .= !empty($_SERVER['HTTPS']) ? 'https' : 'http';

    //HOST
    $root .= '://' . $_SERVER['HTTP_HOST'];

    //ALIAS
    if(!empty($_SERVER['CONTEXT_PREFIX'])) {
        $root .= $_SERVER['CONTEXT_PREFIX'];
        $root .= substr($dir, strlen($_SERVER[ 'CONTEXT_DOCUMENT_ROOT' ]));
    } else {
        $root .= substr($dir, strlen($_SERVER[ 'DOCUMENT_ROOT' ]));
    }

    //$root .= '/';

    return $root;
}

function getFile($a_file)
{
	header("Content-Type: application/octet-stream");
	header("Pragma: no-cache");
	header("Expires: 0");

	readfile($a_file->getRealPath());
}
?>
