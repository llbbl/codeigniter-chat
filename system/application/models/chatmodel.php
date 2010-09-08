<?php


class chatmodel extends Model
{
	function __construct()
	{
		parent::Model(); 
	}
	
	
	function getMsg($limit = 10)
	{
		$sql = "SELECT * FROM messages ORDER BY id DESC LIMIT $limit";		
		return $this->db->query($sql);
	}
	
	function insertMsg($name, $message, $current)
	{
		$sql = "INSERT INTO messages SET user='$name', msg='$message', time='$current'";
		return $this->db->query($sql);
	}
}

