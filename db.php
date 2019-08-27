<?php


class db
{
	public $con;
	public $last_id;
	public $error="";
	public $nb_rows=0;
	public $rows=array();
	public $row,$rows_array;
	function __construct($server= DB_SERVER,$username = DB_SERVER_USERNAME,$password = DB_SERVER_PASSWORD,$database = DB_DATABASE) 
	{	
		
		
		$this->con = mysqli_connect($server, $username, $password, $database);

		if (!$this->con) {
			$error="Error: Unable to connect to MySQL." . PHP_EOL.
			 "Debugging errno: " . mysqli_connect_errno() . PHP_EOL.
			 "Debugging error: " . mysqli_connect_error() . PHP_EOL;
			$this->db_error("Database select",mysqli_connect_errno(),$error);
			exit;
		}
	
	
		return $this->con;
	}
	function num_rows($result)
	{
		return mysqli_num_rows($result);
	}
	function db_close() {		
		return (mysqli_close($this->con)) or false;
	}
	
	function db_error($query, $errno, $error) { 
		
		die('<font color="#000000"><b>' . $errno . ' - ' . $error . '<br><br>' . $query . '<br><br><small><font color="#ff0000">[TEP STOP]</font></small><br><br></b></font>');
		
	}
	
	function db_query($query) {
		
		$result = mysqli_query($this->con,$query) or $this->db_error($query, mysqli_errno($this->con), mysqli_error($this->con));
		
		return $result;
	}
	
	function db_query2($query) {
		
		mysqli_query($this->con,$query);
	}
	function db_num_query($query)
	{
		return $this->db_num($this->db_query($query));
	}
	function count_table($db_table,$where)
	{
		$countSql = "SELECT COUNT(id) FROM ".$db_table.$where;  
		$tot_result = $this->db_query($countSql);    
		$row = mysqli_fetch_row($tot_result) ;
		return $row[0];
	}
	
	
	function db_num($result) 
	{
    	return mysqli_num_rows($result);
  	}
	function last_insert_id()
	{		
		return mysqli_insert_id($this->con);
	}
	function __destruct(){
		if(is_resource($this->con) && get_resource_type($this->con) === 'mysqli link')
			$this->db_close();
    	unset($this->con); 
	}
	function mysqli_field_name($result, $field_offset)
	{
		$properties = mysqli_fetch_field_direct($result, $field_offset);
		return is_object($properties) ? $properties->name : null;
	}
	//return rows list or one element
	 //example :db_select("article","*",array('id'=>'5')) , db_select("article",array('title'),'id=5') 
	 //db_select("article",'title','id=5',array('id','asc'),1,20) 
	function db_select($table,$fields_array_or_strig,$where="",$sufix="")
	{
		
		$this->nb_rows=0;
		unset($this->row);
		unset($this->rows);
		$this->row=array();
		$this->rows=array();
		if(empty($fields_array_or_strig))
			return false;
		if(is_array($fields_array_or_strig))
			$fields_array_or_strig=implode(",",$fields_array_or_strig);
		$where=$this->getWhere($where);
		$sufix=str_replace("order by ","",$sufix);
		if($sufix!="")
		$sufix=" order by ".$sufix;
		$where=str_replace(" where ","",$where);
		if($where!="")
		$where=" where ".$where;
		/*$sortby="";
		if(is_array($sort_by))
			$sortby=" order by ".$sort_by[0]." ".$sort_by[1];
		elseif($sort_by)
			$sortby=" order by ".$sort_by;
		$limit="";
		if(!empty($limite_from)) 
			$limit=" LIMIT ".$limite_from;
		if(!empty($limite_to)) 
			$limit.=" , ".$limite_to;*/
	
		
		$sql="select ".$fields_array_or_strig." from ".$table.$where.$sufix;
		
		
		$resulta = $this->db_query($sql); 
		$rows=array();
		while($data = mysqli_fetch_assoc($resulta))
		{
			$this->nb_rows++;
			$this->rows[]=$data;
			$arr=array();
			foreach($data as $key=>$val)
			if(($key=="created")||($key=="updated"))
				$arr[]=ago($val);
			else
				$arr[]=$val;
			$this->rows_array[]=$arr;
			if($this->nb_rows==1)
				$this->row=$data;
			
		}
		return $this->rows;
		
	}
	function db_select_one_col($table,$field,$where="",$sufix="")
	{
		$this->nb_rows=0;
		unset($this->row);
		unset($this->rows);
		$this->row=array();
		$this->rows=array();
		
		$fields_array_or_strig=$field;
		$where=$this->getWhere($where);
		$sufix=str_replace("order by ","",$sufix);
		if($sufix!="")
		$sufix=" order by ".$sufix;
		$where=str_replace(" where ","",$where);
		if($where!="")
		$where=" where ".$where;
	
		
		$sql="select ".$fields_array_or_strig." from ".$table.$where.$sufix;
		//die($sql);
		$resulta = $this->db_query($sql); 
		$rows=array();
		while($data = mysqli_fetch_assoc($resulta))
		{
			$this->nb_rows++;
			$this->rows[]=$data[$field];
			
			
		}
		return $this->rows;
		
	}
	function db_selectOne($table,$fields_array_or_strig,$where="")
	{
		
		if($where)
		$sql="select ".join_fields($fields_array_or_strig)." from ".$table." where ".join_fields($where,"and");
		else
		$sql="select ".join_fields($fields_array_or_strig)." from ".$table;
		if (strpos($sql, 'limit 1') === false) {
			$sql.=" limit 1";
		}
		$resulta = $this->db_query($sql);
		if($data = mysqli_fetch_assoc($resulta))		
			return $data;
		else
			return false;
	}
	
	function db_insert($table,$fields_array)
	{
		if(empty($fields_array))
			return false;
		$tit="";
		$val="";
		foreach($fields_array as $f=>$v)
		{
			if($tit!="")
			{
				$tit.=",";
				$val.=",";
			}
			$tit.=$f;
			$val.="'".$this->db_string($v,$this->con)."'";
		}
		
		$this->db_query("insert into ".$table." (".$tit.") values (".$val.")");
		$this->last_id=$this->last_insert_id();
	}
	function db_update($table,$fields_array,$where,$fields_without_quotes=array())
	{
		if(empty($fields_array)&&empty($fields_without_quotes))
			return false;
		$where=$this->getWhere($where);
		$edit_val="";
		if(!is_array($fields_array))
			$edit_val=$fields_array;
		else
		foreach($fields_array as $f=>$v)
		{
			if($edit_val!="")
			{
				
				$edit_val.=",";
			}
			$edit_val.=$f."="."'".$this->db_string($v,$this->con)."'";
		}
		foreach($fields_without_quotes as $f=>$v)
		{
			if($edit_val!="")
			{
				
				$edit_val.=",";
			}
			$edit_val.=$f."=".$v;
		}
		
		$this->db_query("update ".$table." set ".$edit_val.$where);
	}
	
	function db_insert2($table,$fields_array)
	{
		if(empty($fields_array))
			return false;
		$tit="";
		$val="";
		foreach($fields_array as $f)
		{
			if($tit!="")
			{
				$tit.=",";
				$val.=",";
			}
			$tit.=$f[0];
			$val.="'".$this->db_string($f[1],$this->con)."'";
		}
		$this->db_query("insert into ".$table." (".$tit.") values (".$val.")");
		$this->last_id=$this->last_insert_id();
	}
	function db_update2($table,$fields_array,$where)
	{
		
		if(empty($fields_array))
			return false;
		$where=$this->getWhere($where);
		$edit_val="";
		foreach($fields_array as $f)
		{
			if($edit_val!="")
			{
				
				$edit_val.=",";
			}
			$edit_val.=$f[0]."="."'".$this->db_string($f[1],$this->con)."'";
		}
		
		$this->db_query("update ".$table." set ".$edit_val.$where);
	}
	function db_delete($table,$id)
	{
		if(!is_array($id))
		$this->db_query("delete from ".$table." where id=".$id);
		else
		foreach($id as $i)
		$this->db_query("delete from ".$table." where id=".$i);
	}
	function db_delete_where($table,$where)
	{
		$where=$this->getWhere($where);
		$this->db_query("delete from ".$table." ".$where);
		
	}
	function db_exist($table,$where)
	{
		$where=$this->getWhere($where);
		$result = $this->db_query ("select * from $table ".$where);
		if($data = mysqli_fetch_assoc($result))
			return $data;
		return false;
	}
	function db_string($string) 
	{
		
	
		if (function_exists('mysql_real_escape_string')) {
		  return mysqli_real_escape_string( $this->con,$string);
		} elseif (function_exists('mysql_escape_string')) {
		  return mysqli_escape_string($string);
		}
	
		return addslashes($string);
  	}
	function db_prepare_input($string) 
	{
		if (is_string($string)) {
		  return trim(stripslashes($string));
		} elseif (is_array($string)) {
		  reset($string);
		  while (list($key, $value) = each($string)) {
			$string[$key] = tep_db_prepare_input($value);
		  }
		  return $string;
		} else {
		  return $string;
		}
  	}
	function convertToutf8()
	{
		
		//$this->db_query("ALTER ".DATABASE DB_DATABASE." CHARACTER SET utf8 COLLATE utf8_unicode_ci");
		$res = $this->db_query("SHOW TABLES");
		while ($row = mysqli_fetch_array($res))
		{
			foreach ($row as $key => $table)
			{
				$this->db_query("ALTER TABLE " . $table . " CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");
			}
		}
	}
	function setUTF8()
	{
		$this->db_query("set character_set_server='utf8'");
		$this->db_query("set names 'utf8'");
	}
	function getWhere($where)
	{
		if(!is_array($where))
		{
			if($where!="")
				return " where ".$where;
			else
			return "";
		}
		$where_val="";
		foreach($where as $f=>$v)
		{
			if($where_val!="")
			{
				
				$where_val.=" and ";
			}
			$where_val.=$f."="."'".$this->db_string($v,$this->con)."'";
		}
		if($where_val!="")
				return " where ".$where_val;
		
	}

}
if(!function_exists("join_fields"))
{
	function join_fields($fields_array_or_strig,$sep=",")
	{
		if(is_array($fields_array_or_strig))
		{
			return join(" ".$sep." ",$fields_array_or_strig);
		}
		return $fields_array_or_strig;
	}
}
?>
