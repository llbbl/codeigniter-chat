<?php

if ($query->num_rows() > 0)
{
   foreach ($query->result() as $row)
   {
   		echo "<b>$row->user</b>:";
   		echo " $row->msg </br>";
   	}
}
   		