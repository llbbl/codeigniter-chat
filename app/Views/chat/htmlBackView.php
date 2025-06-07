<?php

if (isset($query) && count($query) > 0) {
    foreach ($query as $row) {
        echo "<b>" . esc($row['user']) . "</b>:";
        echo " " . esc($row['msg']) . " </br>";
    }
}
