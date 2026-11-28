<?php
/*
 * regression tests (no dependency)
 * usage: php tests/run.php
 */

require_once dirname(__FILE__).'/../IO/Bit.php';

$failed = 0;
$passed = 0;
$root = dirname(dirname(__FILE__));
$datadir = dirname(__FILE__).'/data';

// any PHP warning/notice raised by the library counts as a failure
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    global $failed;
    $failed++;
    echo "FAIL: PHP error: $errstr ($errfile:$errline)".PHP_EOL;
    return true;
});

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

/*
 * test data (tests/data/, generated by tests/data/make.php)
 */
function data($name) {
    global $datadir;
    return file_get_contents("$datadir/$name");
}

// integers: values written by make.php in both byte orders
$ints_u = array(
    array('UI8', array(0x00, 0x7f, 0x80, 0xff)),
    array('UI16', array(0x0000, 0x7fff, 0x8000, 0xffff, 0x1234)),
    array('UI24', array(0x123456, 0x800000, 0xfffffe)),
    array('UI32', array(0x12345678, 0x7fffffff, 0x80000000, 0xfffffffe)),
);
$ints_s = array(
    array('SI8', array(0, 127, -128, -1)),
    array('SI16', array(0, 0x7fff, -0x8000, -1, 0x1234)),
    array('SI24', array(0x123456, -0x800000, -2)),
    array('SI32', array(0x12345678, 0x7fffffff, -0x80000000, -2)),
);
if (PHP_INT_SIZE >= 8) {
    // 2^64-2 does not fit in int: returned as float (documented)
    $ints_u[] = array('UI64', array(0x0102030405060708, PHP_INT_MAX,
                                    (float) 0xfffffffffffffffe));
    $ints_s[] = array('SI64', array(0x0102030405060708, PHP_INT_MAX, -2));
}
foreach (array('BE', 'LE') as $endian) {
    $file = 'ints_'.strtolower($endian).'.bin';
    foreach (array($ints_u, $ints_s) as $table) {
        $r = reader(data($file));
        foreach ($table as $t) {
            list($type, $values) = $t;
            $method = 'get'.$type.(($type === 'UI8' || $type === 'SI8') ? '' : $endian);
            foreach ($values as $i => $v) {
                check("$file $method #$i", $v, $r->$method());
            }
        }
        if (PHP_INT_SIZE >= 8) {
            check("$file end offset", array(63, 0), $r->getOffset());
            check("$file hasNextData at end", false, $r->hasNextData());
            checkThrows("$file read past end", function() use ($r) {
                $r->getUI8();
            });
        }
    }
    // put* writes the same bytes (24/64 bit have no put method)
    $w = new IO_Bit();
    foreach (array(0x00, 0x7f) as $v) { $w->putUI8($v); }
    foreach (array(-128, -1) as $v) { $w->putSI8($v); }
    $m16 = 'putUI16'.$endian; $s16 = 'putSI16'.$endian;
    $m32 = 'putUI32'.$endian; $s32 = 'putSI32'.$endian;
    $w->$m16(0x0000); $w->$m16(0x7fff); $w->$s16(-0x8000); $w->$s16(-1); $w->$m16(0x1234);
    $head = $w->output();
    check("$file put* 8/16 bit", substr(data($file), 0, 14), $head);
    $w = new IO_Bit();
    $w->$m32(0x12345678); $w->$s32(0x7fffffff); $w->$m32(0x80000000); $w->$s32(-2);
    check("$file put* 32 bit", substr(data($file), 23, 16), $w->output());
}

// bit fields: array(width, unsigned, signed)
$fields = array(array(1, 1, -1), array(3, 5, -3), array(4, 0, 0),
                array(5, 0x1d, -3), array(7, 0x55, -43),
                array(12, 0xabc, -1348), array(1, 0, 0),
                array(16, 0x8001, -32767), array(3, 7, -1));
foreach (array('' => 'bits_msb.bin', 'LSB' => 'bits_lsb.bin') as $sfx => $file) {
    foreach (array('UI' => 1, 'SI' => 2) as $sign => $col) {
        $r = reader(data($file));
        $method = "get{$sign}Bits$sfx";
        foreach ($fields as $i => $f) {
            check("$file $method($f[0]) #$i", $f[$col], $r->$method($f[0]));
        }
        check("$file $method offset", array(6, 4), $r->getOffset());
        check("$file hasNextData 1 byte (half left + 1)", true, $r->hasNextData(1, 4));
        check("$file hasNextData beyond", false, $r->hasNextData(1, 5));
        check("$file aligned byte after bits", 0xa5, $r->getUI8());
        check("$file hasNextData at end", false, $r->hasNextData());
        checkThrows("$file get bit at end", function() use ($r, $sfx) {
            $m = "getUIBit$sfx";
            $r->$m();
        });
    }
    // single-bit reads give the bit order of the first byte
    $r = reader(data($file));
    $m = "getUIBit$sfx";
    $first = '';
    for ($i = 0; $i < 8; $i++) { $first .= $r->$m(); }
    $d = data($file);
    $byte = ord($d[0]);
    check("$file getUIBit$sfx order", $sfx ? strrev(sprintf('%08b', $byte)) : sprintf('%08b', $byte), $first);
    // put*Bits builds the same file
    $w = new IO_Bit();
    foreach ($fields as $i => $f) {
        $m = ($i % 2) ? "putSIBits$sfx" : "putUIBits$sfx";
        $w->$m($f[($i % 2) ? 2 : 1], $f[0]);
    }
    $w->putUI8(0xa5);
    check("$file put*Bits$sfx build", data($file), $w->output());
}
check('getUIBits(0)', 0, reader("\xff")->getUIBits(0));
check('getUIBitsLSB(0)', 0, reader("\xff")->getUIBitsLSB(0));
check('getSIBits(0)', 0, reader("\xff")->getSIBits(0));
$r = reader("\xff\x01");
$r->getUIBits(3);
check('getUIBits across bytes', 0x3e, $r->getUIBits(6)); // 11111 + 0
checkThrows('getUIBits past end', function() {
    reader("\xff")->getUIBits(9);
});
checkThrows('getUIBitsLSB past end', function() {
    reader("\xff")->getUIBitsLSB(9);
});

// byte strings
$r = reader(data('strings.bin'));
check('getData', 'HEAD', $r->getData(4));
check('getDataUntil NUL', 'abc', $r->getDataUntil("\0"));
check('getDataUntil CRLF', 'de', $r->getDataUntil("\r\n"));
check('getDataUntil CRLF 2', 'fg', $r->getDataUntil("\r\n"));
check('getDataUntil empty field', '', $r->getDataUntil("\0"));
check('getDataUntil offset after delimiter', array(17, 0), $r->getOffset());
check('getData NUL', "\0", $r->getData(1));
check('getDataUntil not found (rest)', 'tail', $r->getDataUntil("\0"));
check('getDataUntil at end', '', $r->getDataUntil("\0"));
check('getData(0) at end', '', $r->getData(0));
checkThrows('getData past end', function() use ($r) { $r->getData(1); });
check('getData past end keeps offset', array(22, 0), $r->getOffset());
$r = reader(data('strings.bin'));
$r->getData(4);
check('getDataUntil null (rest)', "abc\0de\r\nfg\r\n\0\0tail", $r->getDataUntil(null));
$r = reader(data('strings.bin'));
$r->getData(4);
check('getDataUntil false (rest)', 18, strlen($r->getDataUntil(false)));
$r = reader("\x80ABC");
$r->getUIBit();
check('getData byte aligns', 'AB', $r->getData(2));
$r = reader("\x80A,B");
$r->getUIBit();
check('getDataUntil byte aligns', 'A', $r->getDataUntil(','));

// BCD
$r = reader(data('bcd.bin'));
foreach (array(0, 9, 10, 42, 99) as $i => $v) {
    check("getUIBCD8 #$i", $v, $r->getUIBCD8());
}
checkThrows('getUIBCD8 at end', function() use ($r) { $r->getUIBCD8(); });
$w = new IO_Bit();
foreach (array(0, 9, 10, 42, 99) as $v) { $w->putUIBCD8($v); }
check('putUIBCD8 build', data('bcd.bin'), $w->output());

// truncated input: every reader throws when the data runs out
$short = array('getUI32BE', 'getUI32LE', 'getSI32BE', 'getSI32LE',
               'getUI64BE', 'getUI64LE', 'getSI64BE', 'getSI64LE');
foreach ($short as $m) {
    checkThrows("truncated $m", function() use ($m) {
        reader(data('truncated.bin'))->$m();
    });
}
check('truncated getUI24BE fits', 0x123456, reader(data('truncated.bin'))->getUI24BE());
check('truncated getUI24LE fits', 0x563412, reader(data('truncated.bin'))->getUI24LE());
$at2 = array('getUI16BE', 'getUI16LE', 'getSI16BE', 'getSI16LE',
             'getUI24BE', 'getUI24LE', 'getSI24BE', 'getSI24LE');
foreach ($at2 as $m) {
    checkThrows("truncated $m at offset 2", function() use ($m) {
        $r = reader(data('truncated.bin'));
        $r->setOffset(2, 0);
        $r->$m();
    });
}
$empty = array('getUI8', 'getSI8', 'getUIBCD8', 'getUIBit', 'getUIBitLSB');
foreach ($empty as $m) {
    checkThrows("empty $m", function() use ($m) {
        reader(data('empty.bin'))->$m();
    });
}
checkThrows('empty getData', function() { reader(data('empty.bin'))->getData(1); });
check('empty getDataUntil', '', reader(data('empty.bin'))->getDataUntil("\0"));
check('empty hasNextData', false, reader(data('empty.bin'))->hasNextData());
check('empty hasNextData(0)', true, reader(data('empty.bin'))->hasNextData(0));
try {
    reader(data('truncated.bin'))->getUI32LE();
} catch (IO_Bit_Exception $e) {
    check('getUI32LE message', 'getUI32LE: 3 < 0 + 4', $e->getMessage());
}

/*
 * offsets
 */
$r = reader("\x01\x02\x03");
check('getOffset initial', array(0, 0), $r->getOffset());
$r->byteAlign();
check('byteAlign when aligned', array(0, 0), $r->getOffset());
$r->getUIBits(3);
check('getOffset after bits', array(0, 3), $r->getOffset());
$r->byteAlign();
check('byteAlign', array(1, 0), $r->getOffset());
$r->setOffset(2, 0);
check('setOffset then read', 0x03, $r->getUI8());
$r->setOffset(0, 7);
check('setOffset bit then read bit', 1, $r->getUIBit());
check('bit read crosses to next byte', array(1, 0), $r->getOffset());
check('hasNextData 2', true, $r->hasNextData(2));
check('hasNextData 3', false, $r->hasNextData(3));
check('hasNextData bits', true, $r->hasNextData(1, 8));
check('hasNextData bits over', false, $r->hasNextData(1, 9));
$r = reader("ABC");
$r->input("XY");
check('input resets offset', array(0, 0), $r->getOffset());
check('input replaces data', 'X', $r->getData(1));

/*
 * put / output
 */
$w = new IO_Bit();
check('output empty', '', $w->output());
$w->putUIBits(1, 1);
check('output includes partial byte', "\x80", $w->output());
$w->putUI8(0x41);
check('put byte aligns', "\x80A", $w->output());
check('offset after put', array(2, 0), $w->getOffset());
$w = new IO_Bit();
$w->putData('AB', 4);
$w->putData('CDEF', 2);
$w->putData('G', 3, '-');
$w->putData('');
check('putData pad/truncate', "AB\0\0CDG--", $w->output());
$w = new IO_Bit();
$w->putUI32BE(0xffffffff);
$w->putSI32LE(-1);
$w->putSI32BE(-2);
check('putUI32BE/putSI32 negative', "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xfe", $w->output());
$w = new IO_Bit();
$w->putSI16BE(-2);
$w->putSI16LE(-2);
check('putSI16BE/LE', "\xff\xfe\xfe\xff", $w->output());
$r = reader("ABCDEF");
check('output after input (offset 0)', '', $r->output());
$r->getData(2);
check('output after read', 'AB', $r->output());
$r->setOffset(6, 0);
check('output at end is all data', 'ABCDEF', $r->output());
$w = new IO_Bit();
$w->putData('ABCDEF');
$w->setOffset(3, 0);
check('output up to offset', 'ABC', $w->output());
$w->setOffset(2, 1);
check('output up to partial byte', 'ABC', $w->output());

/*
 * set (overwrite in place, offset unchanged)
 */
$w = new IO_Bit();
$w->putData(str_repeat("\0", 8));
$w->setUI8(0x11, 0);
$w->setUI16BE(0x2233, 1);
$w->setUI16LE(0x4455, 3);
check('set does not move offset', array(8, 0), $w->getOffset());
check('setUI8/16BE/16LE', "\x11\x22\x33\x55\x44\0\0\0", $w->output());
$w->setUI32BE(0x01020304, 0);
$w->setUI32LE(0x01020304, 4);
check('setUI32BE/LE', "\x01\x02\x03\x04\x04\x03\x02\x01", $w->output());
$w->setData('XYZ', 1, 2);
$w->setData('Q', 5, 3, '-');
check('setData data_len', "\x01XY\x04\x04Q--", $w->output());
$w->setUIBits(0xf, 4, 0, 4);
check('setUIBits low nibble', "\x0fXY\x04\x04Q--", $w->output());
$w->setUIBits(0, 4, 0, 4);
check('setUIBits clears', "\x00XY\x04\x04Q--", $w->output());
$w->setUIBits(0x1ff, 9, 0, 12);  // bit offset >= 8 moves to next byte
check('setUIBits bit offset over 8', "\x00\x5f\xf9", substr($w->output(), 0, 3));

/*
 * hexdump
 */
$r = reader(implode('', array_map('chr', range(0x00, 0x2f))));
ob_start();
$r->hexdump(0, 0x20);
$lines = explode(PHP_EOL, ob_get_clean());
check('hexdump header', true, strpos($lines[0], '0  1  2  3') !== false);
check('hexdump address 0', 0, strpos($lines[1], '0x00000000  00 01 02 03 04 05 06 07  08 09'));
check('hexdump address 0x10', 0, strpos($lines[2], '0x00000010  10 11'));
check('hexdump line count', 4, count($lines)); // header, 2 rows, ''
check('hexdump non-printable as space', true, substr($lines[1], -16) === str_repeat(' ', 16));
ob_start();
$r->hexdump(0x21, 3);
$lines = explode(PHP_EOL, ob_get_clean());
check('hexdump unaligned offset address', 0, strpos($lines[1], '0x00000020 '));
check('hexdump unaligned offset ascii', true, substr(rtrim($lines[1]), -3) === '!"#');
ob_start();
$r->hexdump(0, 0x30, 4);
$out = ob_get_clean();
check('hexdump limit', true, strpos($out, '...(truncated)...') !== false);
check('hexdump limit stops', false, strpos($out, '04 '));
ob_start();
$r->hexdump(0x28, 0x10);  // runs past the end of data
$out = ob_get_clean();
check('hexdump past end', true, strpos($out, '2f ') !== false);

/*
 * samples
 */
function runSample($args, $stdin = '') {
    global $root;
    $cmd = escapeshellarg(PHP_BINARY).' -d error_reporting='.E_ALL.' -d display_errors=stderr'.
           ' -d include_path='.escapeshellarg($root.PATH_SEPARATOR.get_include_path()).
           ' '.$args;
    $proc = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'),
                                  2 => array('pipe', 'w')), $pipes, $root);
    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return array(proc_close($proc), $out, $err);
}
list($st, $out, $err) = runSample('sample/iobit_put.php 1:0 2:1 3:7 4:0 5:31 6:46 7:6 8:135');
check('iobit_put.php status', 0, $st);
check('iobit_put.php stderr', '', $err);
check('iobit_put.php output', data('sample_put.bin'), $out);
list($st, $out, $err) = runSample('sample/iobit_get.php tests/data/sample_put.bin 1 2 3 4 5 6 7 8');
check('iobit_get.php status', 0, $st);
check('iobit_get.php stderr', '', $err);
check('iobit_get.php output', "1:0 2:1 3:7 4:0 5:31 6:46 7:6 8:135 ".PHP_EOL, $out);
list($st, $out, $err) = runSample('sample/iobit_get.php - 4 4 8', data('bcd.bin'));
check('iobit_get.php stdin', "4:0 4:0 8:9 ".PHP_EOL, $out);
check('iobit_get.php stdin stderr', '', $err);

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
