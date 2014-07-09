<?php

require_once 'IO/Bit.php';

if ($argc < 2)  {
    echo "Usage: php iobit_put.php <width>:<value> [<width2>:<value2> [...]]".PHP_EOL;
    echo "ex) php iobit_put.php 1:0 2:1 3:7 4:0 5:31 6:46 7:6 8:135".PHP_EOL;
    exit(1);
}

$iobit = new IO_Bit();
foreach (array_slice($argv, 1) as $arg) {
    list($width, $value) = explode(':', $arg);
    $value = $iobit->putUIBits($value, $width);
}
echo $iobit->output();



