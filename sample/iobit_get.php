<?php

require_once 'IO/Bit.php';

function usage() {
    echo "Usage: php iobit_get.php <filename> <width> [<width2> [...]]".PHP_EOL;
    echo "ex) php iobit_get.php iobit_get.php 1 2 3 4 5 6 7 8".PHP_EOL;

}
if ($argc < 2)  {
    usage();
    exit(1);
}

$filename = $argv[1];
if ($filename === '-') {
    $filename = 'php://stdin';
} else {
    if (is_readable($filename) === false) {
        usage();
        exit(1);
    }
}

$iobit = new IO_Bit();
$filedata = file_get_contents($filename);
$iobit->input($filedata);
foreach (array_slice($argv, 2) as $arg) {
    $value = $iobit->getUIBits($arg);
    echo "$arg:$value ";
}
echo PHP_EOL;
