<?php

if (isset($query) && count($query) > 0) {
    foreach ($query as $row) {
        echo "<b>{$row['user']}</b>:";
        echo " {$row['msg']} </br>";
    }
}
