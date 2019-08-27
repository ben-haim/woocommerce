<?php
if(!defined("DB_SERVER_USERNAME"))
{
	require_once("config.php");
	
	require_once("db.php");
	
	$db=new db();
	if($db->error)
	die($db->error);
}
global $db;

?>
