<?php

  /*
   * 2010/07/28- (c) yoya@awm.jp
   */

require_once dirname(__FILE__).'/Bit/Exception.php';

class IO_Bit {
    /*
     * instance variable
     */
    var $_data = '';
    var $_byte_offset = 0;
    var $_bit_offset = 0;

    /*
     * data i/o method
     */
    function input($data) {
        $this->_data = $data;
        $this->_byte_offset = 0;
        $this->_bit_offset = 0;
    }
    function output($offset = 0) {
        $output_len = $this->_byte_offset;
        if ($this->_bit_offset > 0) {
            $output_len++;
        }
        if (strlen($this->_data) == $output_len) {
            return $this->_data;
        }
        return substr($this->_data, $offset, $output_len);
    }

    /*
     * offset method
     */
    function hasNextData($byte_len = 1, $bit_len = 0) {
        $byte_offset = $this->_byte_offset + $byte_len;
        $bit_offset  = $this->_bit_offset  + $bit_len;
        if (strlen($this->_data) < ($byte_offset + ($bit_offset / 8))) {
            return false;
        }
        return true;
    }
    function setOffset($byte_offset, $bit_offset) {
        $this->_byte_offset = $byte_offset;
        $this->_bit_offset  = $bit_offset;
        return true;
    }
    function incrementOffset($byte_offset, $bit_offset) {
        if ($bit_offset < 0) {
            $byte_offset -= (-$bit_offset + 7) >> 3;
            $bit_offset = ($bit_offset % 8) + 8;
        }
        $byte_offset += $this->_byte_offset;
        $bit_offset += $this->_bit_offset;
        if  ($bit_offset < 8) {
            $this->_byte_offset = $byte_offset;
            $this->_bit_offset  = $bit_offset;
            
        } else {
            $this->_byte_offse = $byte_offset + ($bit_offset >> 3);
            $this->_bit_offset = $bit_offset & 7;
        }
        return true;
    }
    function getOffset() {
        return array($this->_byte_offset, $this->_bit_offset); 
    }
    function byteAlign() {
        if ($this->_bit_offset > 0) {
            $this->_byte_offset ++;
            $this->_bit_offset = 0;
        }
    }
    
    /*
     * get method
     */
    function getData($length) {
        $this->byteAlign();
        if (strlen($this->_data) < $this->_byte_offset + $length) {
            $data_len = strlen($this->_data);
            $offset = $this->_byte_offset;
            throw new IO_Bit_Exception("getData: $data_len < $offset + $length");
        }
        $data = substr($this->_data, $this->_byte_offset, $length);
        $data_len = strlen($data);
        $this->_byte_offset += $data_len;
        return $data;
    }
    function getDataUntil($delimiter) {
        $this->byteAlign();
        if (($delimiter === false) || is_null($delimiter)) {
            $pos = false;
        } else {
            $pos = strpos($this->_data, $delimiter, $this->_byte_offset);
        }
        if ($pos === false) {
            $length = strlen($this->_data) - $this->_byte_offset;
            $delim_len = 0;
        } else {
            $length = $pos - $this->_byte_offset;
            $delim_len = strlen($delimiter);
        }
        $data = $this->getData($length);
        if ($delim_len > 0) {
            $this->_byte_offset += $delim_len;
        }
        return $data;
    }
    function getUI8() {
        $this->byteAlign();
        if (strlen($this->_data) < $this->_byte_offset + 1) {
            $data_len = strlen($this->_data);
            $offset = $this->_byte_offset;
            throw new IO_Bit_Exception("getUI8: $data_len < $offset + 1");
        }
        $value = ord($this->_data{$this->_byte_offset});
        $this->_byte_offset += 1;
        return $value;
    }
    function getSI8() {
        $value = $this->getUI8();
        if ($value < 0x80) {
            return $value;
        }
        return $value - 0x100; // 2-negative
    }
    function getUI16BE() {
        $this->byteAlign();
        if (strlen($this->_data) < $this->_byte_offset + 2) {
            $data_len = strlen($this->_data);
            $offset = $this->_byte_offset;
            throw new IO_Bit_Exception("getUI16BE: $data_len < $offset + 2");
        }
        $ret = unpack('n', substr($this->_data, $this->_byte_offset, 2));
        $this->_byte_offset += 2;
        return $ret[1];
    }
    function getSI16BE() {
        $value = $this->getUI16BE();
        if ($value < 0x8000) {
            return $value;
        }
        return $value - 0x10000; // 2-negative
    }
    function getUI32BE() {
        $value = $this->getSI32BE(); // PHP bugs
        if ($value < 0) {
            $value += 4294967296;
        }
        return $value;
    }
    function getSI32BE() {
        $this->byteAlign();
        if (strlen($this->_data) < $this->_byte_offset + 4) {
            $data_len = strlen($this->_data);
            $offset = $this->_byte_offset;
            throw new IO_Bit_Exception("getUI32BE: $data_len < $offset + 4");
        }
        $ret = unpack('N', substr($this->_data, $this->_byte_offset, 4));
        $this->_byte_offset += 4;
        $value = $ret[1];
        return $value;
    }


    function getUI16LE() {
        $this->byteAlign();
        if (strlen($this->_data) < $this->_byte_offset + 2) {
            $data_len = strlen($this->_data);
            $offset = $this->_byte_offset;
            throw new IO_Bit_Exception("getUI16LE: $data_len < $offset + 2");
        }
        $ret = unpack('v', substr($this->_data, $this->_byte_offset, 2));
        $this->_byte_offset += 2;
        return $ret[1];
    }
    function getSI16LE() {
        $value = $this->getUI16LE();
        if ($value < 0x8000) {
            return $value;
        }
        return $value - 0x10000; // 2-negative
    }
    function getUI32LE() {
        $value = $this->getSI32LE(); // PHP bugs
        if ($value < 0) {
            $value += 4294967296;
        }
        return $value;
    }
    function getSI32LE() {
        $this->byteAlign();
        if (strlen($this->_data) < $this->_byte_offset + 4) {
            $data_len = strlen($this->_data);
            $offset = $this->_byte_offset;
            throw new IO_Bit_Exception("getUI32LE: $data_len < $offset + 4");
        }
        $ret = unpack('V', substr($this->_data, $this->_byte_offset, 4));
        $this->_byte_offset += 4;
        $value = $ret[1];
        return $value; // PHP bug
    }
    function getUI64LE() {
        $value = $this->getUI32LE();
        $value = $value + 0x100000000 * $this->getUI32LE();
        return $value;
    }
    function getSI64LE() {
        $value = $this->getUI64LE();
	if ($value >= 0x8000000000000000) {
	  $value = 0x7fffffffffffffff - $value;
	}
        return $value;
    }

    // Binary-coded decimal
    function getUIBCD8() {
        $this->byteAlign();
        if (strlen($this->_data) < $this->_byte_offset + 1) {
            $data_len = strlen($this->_data);
            $offset = $this->_byte_offset;
            throw new IO_Bit_Exception("getBCD8: $data_len < $offset + 1");
        }
        $value = ord($this->_data{$this->_byte_offset});
        $value = ($value >> 4 )* 10 + ($value & 0x0f);
        $this->_byte_offset += 1;
        return $value;
    }

    // start with the MSB(most-significant bit)
    function getUIBit() {
        $byte_offset = $this->_byte_offset;
        $bit_offset = $this->_bit_offset;
        $data_len = strlen($this->_data);
        if ($data_len <= $byte_offset) {
            throw new IO_Bit_Exception("getUIBit: $data_len <= $byte_offset");
        }
        $value = ord($this->_data{$byte_offset});
        $value = 1 & ($value >> (7 - $bit_offset)); // MSB(Bit) first
        $bit_offset ++;
        if ($bit_offset < 8) {
            $this->_byte_offset = $byte_offset;
            $this->_bit_offset = $bit_offset;
        } else {
            $this->_byte_offset = $byte_offset + 1;
            $this->_bit_offset = 0;
        }
        return $value;
    }
    function getUIBits($width) {
        $value = 0;
        while ($width--) {
            $value <<= 1;
            $value |= $this->getUIBit();
        }
        return $value;
    }
    function getSIBits($width) {
        $value = $this->getUIBits($width);
        $msb = $value & (1 << ($width - 1));
        if ($msb) {
            $bitmask = (2 * $msb) - 1;
            $value = - ($value ^ $bitmask) - 1;
        }
        return $value;
    }

    // start with the LSB(least significant bit)
    function getUIBitLSB() {
        $byte_offset = $this->_byte_offset;
        $bit_offset = $this->_bit_offset;
        $data_len = strlen($this->_data);
        if ($data_len <= $byte_offset) {
            throw new IO_Bit_Exception("getUIBitLSB: $data_len <= $byte_offset");
        }
        $value = ord($this->_data{$byte_offset});
        $value = 1 & ($value >> $bit_offset); // LSB(Bit) first
        $bit_offset ++;
        if ($bit_offset < 8) {
            $this->_byte_offset = $byte_offset;
            $this->_bit_offset = $bit_offset;
        } else {
            $this->_byte_offset = $byte_offset + 1;
            $this->_bit_offset = 0;

        }
        return $value;
    }
    function getUIBitsLSB($width) {
        $value = 0;
        for ($i = 0 ; $i < $width ; $i++) {
            $value |= $this->getUIBitLSB() << $i; // LSB(Bit) order
        }
        return $value;
    }
    function getSIBitsLSB($width) {
        $value = $this->getUIBitsLSB($width);
        $msb = $value & (1 << ($width - 1));
        if ($msb) {
            $bitmask = (2 * $msb) - 1;
            $value = - ($value ^ $bitmask) - 1;
        }
        return $value;
    }

    /*
     * put method
     */
    function putData($data, $data_len = null, $pad_string = "\0") {
        $this->byteAlign();
        if (is_null($data_len)) {
            $this->_data .= $data;
            $this->_byte_offset += strlen($data);
        } else {
            $len = strlen($data);
            if ($len === $data_len) {
                $this->_data .= $data;
            } elseif ($len < $data_len) {
                $this->_data .= str_pad($data, $data_len, $pad_string);
            } else {
                $this->_data .= substr($data, 0, $data_len);
            }
            $this->_byte_offset += $data_len;
        }
        return true;
    }
    function putUI8($value) {
        $this->byteAlign();
        $this->_data .= chr($value);
        $this->_byte_offset += 1;
        return true;
    }
    function putSI8($value) {
        if ($value < 0) {
            $value = $value + 0x100; // 2-negative reverse
        }
        return $this->putUI8($value);
    }
    function putUI16BE($value) {
        $this->byteAlign();
        $this->_data .= pack('n', $value);
        $this->_byte_offset += 2;
        return true;
    }
    function putUI32BE($value) {
        $this->byteAlign();
        $this->_data .= pack('N', $value);
        $this->_byte_offset += 4;
        return true;
    }
    function putUI16LE($value) {
        $this->byteAlign();
        $this->_data .= pack('v', $value);
        $this->_byte_offset += 2;
        return true;
    }
    function putSI16LE($value) {
        if ($value < 0) {
            $value = $value + 0x10000; // 2-negative reverse
        }
        return $this->putUI16LE($value);
    }
    function putUI32LE($value) {
        $this->byteAlign();
        $this->_data .= pack('V', $value); // XXX
        $this->_byte_offset += 4;
        return true;
    }
    function putSI32LE($value) {
        return $this->putUI32LE($value); // XXX
    }
    function _allocData($need_data_len = null) {
        if (is_null($need_data_len)) {
            $need_data_len = $this->_byte_offset;
        }
        $data_len = strlen($this->_data);
        if ($data_len < $need_data_len) {
            $this->_data .= str_pad(chr(0), $need_data_len - $data_len);
        }
        return true;
    }

    // Binary-coded decimal
    function putUIBCD8($value) {
        $this->byteAlign();
        $value1 = $value % 10;
        $value2 = ($value - $value1)/10;
        $value = ($value2 << 4) + $value1;
        $this->_data .= chr($value);
        $this->_byte_offset += 1;
        return true;
    }

    // start with the MSB(most-significant bit)
    function putUIBit($bit) {
        $this->_allocData($this->_byte_offset + 1);
        if ($bit > 0) {
            $value = ord($this->_data{$this->_byte_offset});
            $value |= 1 << (7 - $this->_bit_offset);  // MSB(Bit) first
            $this->_data{$this->_byte_offset} = chr($value);
        }
        $this->_bit_offset += 1;
        if (8 <= $this->_bit_offset) {
            $this->_byte_offset += 1;
            $this->_bit_offset  = 0;
        }
        return true;
    }
    function putUIBits($value, $width) {
        while ($width--) {
            $bit = ($value >> $width) & 1;
            $ret = $this->putUIBit($bit);
            if ($ret !== true) {
                return $ret;
            }
        }
        return true;
    }
    function putSIBits($value, $width) {
        if ($value < 0) {
            $msb = 1 << ($width - 1);
            $bitmask = (2 * $msb) - 1;
            $value = (-$value  - 1) ^ $bitmask;
        }
        return $this->putUIBits($value, $width);
    }

    // start with the LSB(least significant bit)
    function putUIBitLSB($bit) {
        $this->_allocData($this->_byte_offset + 1);
        if ($bit > 0) {
            $value = ord($this->_data{$this->_byte_offset});
            $value |= 1 << $this->_bit_offset;  // LSB(Bit) first
            $this->_data{$this->_byte_offset} = chr($value);
        }
        $this->_bit_offset += 1;
        if (8 <= $this->_bit_offset) {
            $this->_byte_offset += 1;
            $this->_bit_offset  = 0;
        }
        return true;
    }
    function putUIBitsLSB($value, $width) {
        for ($i = 0 ;  $i < $width ; $i--) { // LSB(Bit) order
            $bit = ($value >> $i) & 1;
            $ret = $this->putUIBit($bit);
            if ($ret !== true) {
                return $ret;
            }
        }
        return true;
    }
    function putSIBitsLSB($value, $width) {
        if ($value < 0) {
            $msb = 1 << ($width - 1);
            $bitmask = (2 * $msb) - 1;
            $value = (-$value  - 1) ^ $bitmask;
        }
        return $this->putUIBits($value, $width);
    }

    /*
     * set method
     */
    function setUI8($value, $byte_offset) {
        $this->_data{$byte_offset + 0} = $data{0};
        return true;
    }
    function setUI16BE($value, $byte_offset) {
        $data = pack('n', $value);
        $this->_data{$byte_offset + 0} = $data{0};
        $this->_data{$byte_offset + 1} = $data{1};
        return true;
    }
    function setUI32BE($value, $byte_offset) {
        $data = pack('N', $value);
        $this->_data{$byte_offset + 0} = $data{0};
        $this->_data{$byte_offset + 1} = $data{1};
        $this->_data{$byte_offset + 2} = $data{2};
        $this->_data{$byte_offset + 3} = $data{3};
        return true;
    }
    function setUI16LE($value, $byte_offset) {
        $data = pack('v', $value);
        $this->_data{$byte_offset + 0} = $data{0};
        $this->_data{$byte_offset + 1} = $data{1};
        return true;
    }
    function setUI32LE($value, $byte_offset) {
        $data = pack('V', $value);
        $this->_data{$byte_offset + 0} = $data{0};
        $this->_data{$byte_offset + 1} = $data{1};
        $this->_data{$byte_offset + 2} = $data{2};
        $this->_data{$byte_offset + 3} = $data{3};
        return true;
    }
    /*
     * need bits
     */
    function need_bits_unsigned($n) {
        for ($i = 0 ; $n ; $i++) {
            $n >>= 1;
        }
        return $i;
    }
    function need_bits_signed($n) {
        if ($n < -1) {
            $n = -1 - $n;
        }
        if ($n >= 0) {
            for ($i = 0 ; $n ; $i++) {
                $n >>= 1;
            }
            $ret = 1 + $i;
        } else { // $n == -1
            $ret = 1;
        }
        return $ret;
    }

    /*
     * general purpose hexdump routine
     */
    function hexdump($offset, $length, $limit = null) {
        printf("             0  1  2  3  4  5  6  7   8  9  a  b  c  d  e  f  0123456789abcdef\n");
        $dump_str = '';
        if ($offset % 0x10) {
            printf("0x%08x ", $offset - ($offset % 0x10));
            $dump_str = str_pad(' ', $offset % 0x10);
        }
        for ($i = 0; $i < $offset % 0x10; $i++) {
            if ($i == 0) {
                echo(' ');
            }
            if ($i == 8) {
                echo(' ');
            }
            echo('   ');
        }
        for ($i = $offset ; $i < $offset + $length; $i++) {
            if ((! is_null($limit)) && ($i >= $offset + $limit)) {
                break;
            }
            if (($i % 0x10) == 0) {
                printf("0x%08x  ", $i);
            }
            if ($i%0x10 == 8) {
                echo(' ');
            }
            if ($i < strlen($this->_data)) {
                $chr = $this->_data{$i};
                $value = ord($chr);
                if ((0x20 < $value) && ($value < 0x7f)) { // XXX: printable
                    $dump_str .= $chr;
                } else {
                    $dump_str .= ' ';
                }
                printf("%02x ", $value);
            } else {
                $dump_str .= ' ';
                echo '   ';
            }
            if (($i % 0x10) == 0x0f) {
                echo " ";
                echo $dump_str;
                echo PHP_EOL;
                $dump_str = '';
            }
        }
        if (($i % 0x10) != 0) {
            echo str_pad(' ', 3 * (0x10 - ($i % 0x10)));
            if ($i < 8) {
                echo ' ';
            }
            echo " ";
            echo $dump_str;
            echo PHP_EOL;
        }
        if ((! is_null($limit)) && ($i >= $offset + $limit)) {
            echo "...(truncated)...".PHP_EOL;
        }
    }
}
