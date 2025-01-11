<?php

require_once 'IO/Bit.php';

$bit = new IO_Bit();
$bit->putData("ABCDE");
$bit->setData("XYZ", 1,2);

echo $bit->output();
