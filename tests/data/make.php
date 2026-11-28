<?php
/*
 * generate synthetic test data for tests/run.php
 * usage: php tests/data/make.php
 *
 * Built with pack()/chr() only (not with IO_Bit), so that the files are
 * an independent reference for the library under test.
 */

$dir = dirname(__FILE__);

// integer fixtures: same values in big and little endian
$u8  = array(0x00, 0x7f, 0x80, 0xff);
$u16 = array(0x0000, 0x7fff, 0x8000, 0xffff, 0x1234);
$u24 = array(0x123456, 0x800000, 0xfffffe);
$u32 = array(0x12345678, 0x7fffffff, 0x80000000, 0xfffffffe);
$u64 = array(array(0x01020304, 0x05060708),  // array(high, low)
             array(0x7fffffff, 0xffffffff),
             array(0xffffffff, 0xfffffffe));

function ints($be, $u8, $u16, $u24, $u32, $u64) {
    $s = '';
    foreach ($u8 as $v)  { $s .= chr($v); }
    foreach ($u16 as $v) { $s .= pack($be ? 'n' : 'v', $v); }
    foreach ($u24 as $v) {
        $s .= $be ? chr($v >> 16).pack('n', $v & 0xffff)
                  : pack('v', $v & 0xffff).chr($v >> 16);
    }
    foreach ($u32 as $v) { $s .= pack($be ? 'N' : 'V', $v); }
    foreach ($u64 as $v) {
        $s .= $be ? pack('NN', $v[0], $v[1]) : pack('VV', $v[1], $v[0]);
    }
    return $s;
}

// bit fields: array(width, unsigned value)
$fields = array(array(1, 1), array(3, 5), array(4, 0), array(5, 0x1d),
                array(7, 0x55), array(12, 0xabc), array(1, 0),
                array(16, 0x8001), array(3, 7));

// pack bit fields (MSB first in byte when $lsb is false, LSB first otherwise)
function bits($fields, $lsb) {
    $bits = '';
    foreach ($fields as $f) {
        list($width, $value) = $f;
        for ($i = 0; $i < $width; $i++) {
            // MSB order: most significant bit of the value first
            $n = $lsb ? $i : ($width - 1 - $i);
            $bits .= ($value >> $n) & 1;
        }
    }
    $bits = str_pad($bits, (int) ceil(strlen($bits) / 8) * 8, '0');
    $s = '';
    foreach (str_split($bits, 8) as $byte) {
        $s .= chr(bindec($lsb ? strrev($byte) : $byte));
    }
    return $s;
}

// output of `php sample/iobit_put.php 1:0 2:1 3:7 4:0 5:31 6:46 7:6 8:135`
$put = array(array(1, 0), array(2, 1), array(3, 7), array(4, 0),
             array(5, 31), array(6, 46), array(7, 6), array(8, 135));

$files = array(
    'ints_be.bin'   => ints(true, $u8, $u16, $u24, $u32, $u64),
    'ints_le.bin'   => ints(false, $u8, $u16, $u24, $u32, $u64),
    // bit fields, zero padding to a byte boundary, then one aligned byte
    'bits_msb.bin'  => bits($fields, false)."\xa5",
    'bits_lsb.bin'  => bits($fields, true)."\xa5",
    'strings.bin'   => "HEAD"."abc\0"."de\r\n"."fg\r\n"."\0\0"."tail",
    'bcd.bin'       => "\x00\x09\x10\x42\x99",
    'truncated.bin' => "\x12\x34\x56",
    'empty.bin'     => '',
    'sample_put.bin' => bits($put, false),
);

foreach ($files as $name => $data) {
    file_put_contents("$dir/$name", $data);
}
