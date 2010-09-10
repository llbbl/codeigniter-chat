<?php

class Chat extends Controller{
	
	function __construct()
	{
		parent::Controller();
		$this->load->model('chatmodel');
	}
	
	function index()
	{		
		$this->load->view('chatView');		
	}
	
	function update()
	{
		if(empty($_POST))
		{
			return false;
		}
		
		foreach($_POST AS $key => $value) {
		    ${$key} = mysql_real_escape_string($value);
		}				

		if($action == "postmsg"){
			$current = time();		
			$this->chatmodel->insertMsg($name, $message, $current);		
		}	
	}
	
	function backend()
	{								
		header("Content-type: text/xml");
		header("Cache-Control: no-cache");
					
		$query = $this->chatmodel->getMsg();
		
		if($query->num_rows()==0){
			$status_code = 2;
		}else{
			$status_code = 1;
		}
				
		echo "<?xml version=\"1.0\"?>\n";
		echo "<response>\n";
		echo "\t<status>$status_code</status>\n";
		echo "\t<time>".time()."</time>\n";
		
		if($query->num_rows()>0){
			foreach($query->result() as $row){				
				$escmsg = htmlspecialchars(stripslashes($row->msg));
				echo "\t<message>\n";
				echo "\t\t<id>$row->id</id>\n";
				echo "\t\t<author>$row->user</author>\n";
				echo "\t\t<text>$escmsg</text>\n";
				echo "\t</message>\n";
			}
		}
		echo "</response>";
				
	}
	
	function json()
	{
		$this->load->view('jsonView');
	}
	
	function json_backend()
	{
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Content-type: application/json');
		
		$query = $this->chatmodel->getMsg();
		
		$data = $query->result_array();
		
		$jsonData = json_encode($data);
		
		echo $jsonData;
	}

	
}
?>