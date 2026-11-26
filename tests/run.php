<?php
/*
 * regression tests (no dependency)
 * usage: php tests/run.php
 */

require_once dirname(__FILE__).'/../IO/Bit.php';

$failed = 0;
$passed = 0;

function check($name, $expect, $actual) {
    global $failed, $passed;
    if ($expect === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL: $name".PHP_EOL;
    echo "  expect: ".var_export($expect, true).PHP_EOL;
    echo "  actual: ".var_export($actual, true).PHP_EOL;
}

function checkThrows($name, $func) {
    global $failed, $passed;
    try {
        $func();
    } catch (IO_Bit_Exception $e) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL: $name (IO_Bit_Exception not thrown)".PHP_EOL;
}

function reader($data) {
    $bit = new IO_Bit();
    $bit->input($data);
    return $bit;
}

/*
 * 64bit integer
 */
if (PHP_INT_SIZE >= 8) {
    check('getUI64BE', 0x0000000100000002,
          reader("\x00\x00\x00\x01\x00\x00\x00\x02")->getUI64BE());
    check('getUI64LE', 0x0000000100000002,
          reader("\x02\x00\x00\x00\x01\x00\x00\x00")->getUI64LE());
    check('getSI64BE positive', 0x0000000100000002,
          reader("\x00\x00\x00\x01\x00\x00\x00\x02")->getSI64BE());
    check('getSI64BE -1', -1, reader(str_repeat("\xff", 8))->getSI64BE());
    check('getSI64LE -1', -1, reader(str_repeat("\xff", 8))->getSI64LE());
    check('getSI64BE -2', -2,
          reader("\xff\xff\xff\xff\xff\xff\xff\xfe")->getSI64BE());
    check('getSI64LE -2', -2,
          reader("\xfe\xff\xff\xff\xff\xff\xff\xff")->getSI64LE());
    check('getSI64BE min', PHP_INT_MIN,
          reader("\x80\x00\x00\x00\x00\x00\x00\x00")->getSI64BE());
    check('getSI64LE max', PHP_INT_MAX,
          reader("\xff\xff\xff\xff\xff\xff\xff\x7f")->getSI64LE());
}

/*
 * LSB bit order
 */
$bit = new IO_Bit();
$bit->putUIBitsLSB(5, 3); // 101
check('putUIBitsLSB', "\x05", $bit->output());
$bit = new IO_Bit();
$bit->putUIBitsLSB(1, 1);
$bit->putUIBitsLSB(6, 3); // 110
check('putUIBitsLSB continuous', "\x0d", $bit->output());
$bit = new IO_Bit();
$bit->putSIBitsLSB(-1, 3); // 111
$bit->putSIBitsLSB(1, 3);  // 001
check('putSIBitsLSB', "\x0f", $bit->output());
$r = reader("\x0f");
check('getSIBitsLSB -1', -1, $r->getSIBitsLSB(3));
check('getSIBitsLSB 1', 1, $r->getSIBitsLSB(3));
check('getSIBitsLSB 0 width', 0, reader("\x00")->getSIBitsLSB(0));
$bit = new IO_Bit();
$bit->putUIBitsLSB(0x2d5, 10);
check('putUIBitsLSB/getUIBitsLSB round trip', 0x2d5,
      reader($bit->output())->getUIBitsLSB(10));

/*
 * set bit
 */
$bit = reader("\xff");
$bit->setUIBit(0, 0, 0);
check('setUIBit 0', "\x7f", $bit->_data);
$bit = reader("\x00");
$bit->setUIBit(1, 0, 7);
check('setUIBit 1', "\x01", $bit->_data);
$bit = reader("\x00");
$bit->setUIBits(5, 3, 0, 0); // 101
check('setUIBits', "\xa0", $bit->_data);
$bit = reader("\xff\xff");
$bit->setUIBits(0x0a, 4, 0, 6); // 1010 over byte boundary
check('setUIBits cross byte', "\xfe\xbf", $bit->_data);

/*
 * NUL padding
 */
$bit = new IO_Bit();
$bit->incrementOffset(3, 0);
$bit->putUIBit(1);
check('putUIBit after skip', "\x00\x00\x00\x80", $bit->output());
$bit = new IO_Bit();
$bit->incrementOffset(3, 0);
$bit->putUIBitLSB(1);
check('putUIBitLSB after skip', "\x00\x00\x00\x01", $bit->output());
$bit = reader("A");
$bit->setUI8(0x42, 3);
check('setUI8 beyond end', "A\x00\x00B", $bit->_data);
$bit = reader("A");
$bit->setUI16BE(0x4243, 2);
check('setUI16BE beyond end', "A\x00BC", $bit->_data);
$bit = reader("A");
$bit->setUI32LE(0x45444342, 2);
check('setUI32LE beyond end', "A\x00BCDE", $bit->_data);
$bit = reader("A");
$bit->setData("XY", 2);
check('setData beyond end', "A\x00XY", $bit->_data);
$bit = reader("");
$bit->setUIBits(1, 1, 1, 7);
check('setUIBits beyond end', "\x00\x01", $bit->_data);

/*
 * incrementOffset
 */
$cases = array(
    // from, increment, expect
    array(array(5, 0), array(0, -8),  array(4, 0)),
    array(array(5, 0), array(0, -16), array(3, 0)),
    array(array(5, 0), array(0, -1),  array(4, 7)),
    array(array(5, 0), array(0, -9),  array(3, 7)),
    array(array(5, 3), array(-1, 0),  array(4, 3)),
    array(array(5, 3), array(-2, 0),  array(3, 3)),
    array(array(5, 3), array(0, -3),  array(5, 0)),
    array(array(5, 3), array(0, -4),  array(4, 7)),
    array(array(5, 3), array(1, 5),   array(7, 0)),
    array(array(5, 3), array(0, 13),  array(7, 0)),
    array(array(5, 3), array(1, -5),  array(5, 6)),
);
foreach ($cases as $c) {
    $bit = new IO_Bit();
    $bit->setOffset($c[0][0], $c[0][1]);
    $bit->incrementOffset($c[1][0], $c[1][1]);
    check('incrementOffset '.json_encode($c[0]).' + '.json_encode($c[1]),
          $c[2], $bit->getOffset());
}

/*
 * need bits
 */
$nb = new IO_Bit();
check('need_bits_unsigned 0', 0, $nb->need_bits_unsigned(0));
check('need_bits_unsigned 4', 3, $nb->need_bits_unsigned(4));
checkThrows('need_bits_unsigned negative', function() use ($nb) {
    $nb->need_bits_unsigned(-1);
});
check('need_bits_signed -1', 1, $nb->need_bits_signed(-1));
check('need_bits_signed -2', 2, $nb->need_bits_signed(-2));
check('need_bits_signed 1', 2, $nb->need_bits_signed(1));

/*
 * exception message
 */
try {
    reader("ab")->getUI24BE();
} catch (IO_Bit_Exception $e) {
    check('getUI24BE message', 'getUI24BE: 2 < 0 + 3', $e->getMessage());
}

/*
 * hexdump
 */
$bit = reader(implode('', array_map('chr', range(0x41, 0x41 + 19))));
ob_start();
$bit->hexdump(0, 20);
$dump = explode(PHP_EOL, ob_get_clean());
check('hexdump ascii column', strpos($dump[1], 'ABCD'), strpos($dump[2], 'QRST'));

/*
 * existing behavior (must not change)
 */
$bit = new IO_Bit();
$bit->putUIBits(5, 3);
$bit->putSIBits(-2, 5);
check('putUIBits/putSIBits', "\xbe", $bit->output());
$r = reader("\xbe");
check('getUIBits', 5, $r->getUIBits(3));
check('getSIBits', -2, $r->getSIBits(5));
$r = reader("\x01\x02\x03\x04\x05\x06\x07\x08");
$r->incrementOffset(4, 0);
$r->incrementOffset(-2, 0);
check('incrementOffset byte back', 0x0304, $r->getUI16BE());
check('getUI32BE', 0xffffffff, reader("\xff\xff\xff\xff")->getUI32BE());
check('getSI32LE', -2, reader("\xfe\xff\xff\xff")->getSI32LE());
check('getSI24BE', -2, reader("\xff\xff\xfe")->getSI24BE());

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
