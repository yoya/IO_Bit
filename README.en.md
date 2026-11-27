IO_Bit
======

This is a library for reading and writing binary data at the byte and bit level.
It is the base of yoya's IO_* libraries such as IO_SWF, IO_JPEG and IO_ISOBMFF.
Not limited in use, free to copy and modify by MIT license.
Please contact me if it doesn't work in your environment or you want new features.

# License

MIT License

# Install

```
% composer require yoya/io_bit
```

# Usage

Use the Composer autoloader as shown below. For a manual installation on the PHP include path, use `require_once 'IO/Bit.php';`.

```php
<?php
require_once dirname(__FILE__) . '/vendor/autoload.php';

// write
$writer = new IO_Bit();
$writer->putUI8(0x47);          // 1 byte
$writer->putUI16BE(0x1234);     // 2 bytes (big endian)
$writer->putUIBits(5, 3);       // 3 bits (MSB first)
$writer->putSIBits(-2, 5);      // 5 bits (signed)
$data = $writer->output();      // "\x47\x12\x34\xbe"

// read
$reader = new IO_Bit();
$reader->input($data);
$reader->getUI8();              // 71
$reader->getUI16BE();           // 4660
$reader->getUIBits(3);          // 5
$reader->getSIBits(5);          // -2
```

- Samples (`sample/`)

```
% php sample/iobit_put.php 1:0 2:1 3:7 4:0 5:31 6:46 7:6 8:135 > out.bin
% php sample/iobit_get.php out.bin 1 2 3 4 5 6 7 8
1:0 2:1 3:7 4:0 5:31 6:46 7:6 8:135
```

# Design overview

`IO_Bit` is a single class that holds the data (`_data`) and the position to read or write (`_byte_offset`, `_bit_offset`).
Reading and writing share the same position.

| Method | Role |
|---|---|
| `input($data)` / `output()` | Set / get the data. `output()` returns from the start up to the current position (including the byte with a partial bit) |
| `getOffset()` / `setOffset($byte, $bit)` / `incrementOffset($byte, $bit)` / `byteAlign()` / `hasNextData($byte, $bit)` | Get, set and move the position, round up to a byte boundary, check the remaining data |
| `getUI8` `getSI8` `getUI16BE` `getUI24BE` `getUI32BE` `getUI64BE` (with `SI` and `LE` variants) | Read integers. BE is big endian, LE is little endian, SI is signed (two's complement) |
| `getData($len)` / `getDataUntil($delim)` | Read bytes |
| `getUIBit` `getUIBits($width)` `getSIBits($width)` | Read bits (MSB first) |
| `getUIBitLSB` `getUIBitsLSB($width)` `getSIBitsLSB($width)` | Read bits (LSB first) |
| `put*` | Writes corresponding to the reads above (no 64-bit or 24-bit) |
| `setUI8` `setUI16BE` `setUI32BE` `setUI16LE` `setUI32LE` `setData` `setUIBit` `setUIBits` | Overwrite at a given position. The current position does not move. The data is extended with NUL if needed |
| `getUIBCD8` / `putUIBCD8` | 1-byte BCD (binary-coded decimal) |
| `need_bits_unsigned($n)` / `need_bits_signed($n)` | Number of bits needed to represent a value |
| `hexdump($offset, $length)` | Print a hex dump |

- Byte-level reads and writes (`getUI8`, `putData`, etc.) round up to a byte boundary before starting.
- Reading past the end of the data throws `IO_Bit_Exception`.
- Byte-level `put*` appends to the end of the data. Use `set*` to overwrite the middle of data that was read.
- 64-bit reads return values within PHP's integer range. Unsigned values of 2^63 or more become floats and lose precision.

- Extending the buffer with `set*` leaves the cursor in place. Move it to the new end before calling `output()` to include the extended bytes.

# Test

```
% php tests/run.php
```

# More detail

- https://github.com/yoya/IO_Bit/wiki
