<?php

require_once 'IO/Bit.php';

$bit = new IO_Bit();
$a = $bit->need_bits_unsigned(2);
$b = $bit->need_bits_unsigned(3);
$c = $bit->need_bits_unsigned(4);

// 2(10) => 2, 3(11) => 2 4(100) => 3
echo "2=>$a 3=>$b 4=>$c";
