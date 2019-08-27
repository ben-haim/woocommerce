<?php



if(!defined("DB_USER"))
{
	
	if(defined("GATEWAY__PLUGIN_DIR"))
		$configPath=GATEWAY__PLUGIN_DIR."../../../wp-config.php";
	else
		$configPath="../../../wp-config.php";
		
	$cnt=delete_spacesNew(file_get_contents($configPath));
	
	if(preg_match("|DB_NAME'(.*?)'(.*?)'(.*?)DB_USER'(.*?)'(.*?)'(.*?)DB_PASSWORD'(.*?)'(.*?)'(.*?)DB_HOST'(.*?)'(.*?)'(.*?)table_prefix(.*?)'(.*?)'|i", $cnt, $row))
	
	{
		define('DB_NAME',$row["2"]);
		define('DB_USER',$row["5"]);
		define('DB_PASSWORD',$row["8"]);		
		define('DB_HOST',$row["11"]);
		$table_prefix=$row["14"];
		
	}
	else
	die("config.php ! wrong parser");
}
define('DB_SERVER_USERNAME',DB_USER);
define('DB_SERVER_PASSWORD',DB_PASSWORD);
define('DB_DATABASE',DB_NAME);
define('DB_SERVER',DB_HOST);

define("table_prefix",$table_prefix);
define("posts_table",$table_prefix."posts");
define("nb_per_page",20);
$Network_Cryptocurrencies=array(
                            'BTC' => 'BTC (Bitcoin)',
                            'BCH' => 'BCH (Bitcoin Cash)',
							'ETH' => 'ETH (Ethereum)',
							'LTC' => 'LTC (Litecoin)',
							'DASH' => 'DASH (Dash)',
							'DOGE' => 'DOGE (Dogecoin)',
							'BSV' => 'BSV (Bitcoin SV)'
							
							);
function delete_spacesNew($content)
{
	$content=str_replace("\n"," ",$content);
	$content=str_replace("\r"," ",$content);
	$content=str_replace("\t"," ",$content);
	return $content;
}

?>
