<?php

function array_insert_array(&$array, $position, $insert_array)
{
    $first_array = array_splice ($array, 0, $position);
    $array = array_merge ($first_array, $insert_array, $array);
}