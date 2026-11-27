IO_Bit
======

English version: [README.en.md](README.en.md)

バイナリをバイト単位・ビット単位で読み書きする為のライブラリです。
IO_SWF、IO_JPEG、IO_ISOBMFF など、yoya の IO_* ライブラリの土台になっています。
利用に制限はかけません。コピーも改変もご自由にどうぞ。MIT ライセンスにしました。
自分の環境だと動かないとか、機能が欲しいとかいった要望があれば連絡下さい。

# License

MIT License

# Install

```
% composer require yoya/io_bit
```

# Usage

Composer でインストールした場合は次のように読み込みます。手動配置の場合は `require_once 'IO/Bit.php';` を使います。

```php
<?php
require_once dirname(__FILE__) . '/vendor/autoload.php';

// 書き込み
$writer = new IO_Bit();
$writer->putUI8(0x47);          // 1 バイト
$writer->putUI16BE(0x1234);     // 2 バイト (ビッグエンディアン)
$writer->putUIBits(5, 3);       // 3 ビット (MSB から)
$writer->putSIBits(-2, 5);      // 5 ビット (符号付き)
$data = $writer->output();      // "\x47\x12\x34\xbe"

// 読み込み
$reader = new IO_Bit();
$reader->input($data);
$reader->getUI8();              // 71
$reader->getUI16BE();           // 4660
$reader->getUIBits(3);          // 5
$reader->getSIBits(5);          // -2
```

- サンプル (`sample/`)

```
% php sample/iobit_put.php 1:0 2:1 3:7 4:0 5:31 6:46 7:6 8:135 > out.bin
% php sample/iobit_get.php out.bin 1 2 3 4 5 6 7 8
1:0 2:1 3:7 4:0 5:31 6:46 7:6 8:135
```

# 設計概要

`IO_Bit` は、データ (`_data`) と、読み書きする位置 (`_byte_offset`, `_bit_offset`) を持つ 1 つのクラスです。
読み込みと書き込みで同じ位置を使います。

| メソッド | 役割 |
|---|---|
| `input($data)` / `output()` | データを渡す / 取り出す。`output()` は先頭から現在の位置まで (途中のビットを含むバイトまで) を返す |
| `getOffset()` / `setOffset($byte, $bit)` / `incrementOffset($byte, $bit)` / `byteAlign()` / `hasNextData($byte, $bit)` | 位置の取得・設定・移動、バイト境界への切り上げ、残りデータの確認 |
| `getUI8` `getSI8` `getUI16BE` `getUI24BE` `getUI32BE` `getUI64BE` (`SI` 版、`LE` 版あり) | 整数の読み込み。BE はビッグエンディアン、LE はリトルエンディアン、SI は符号付き (2 の補数) |
| `getData($len)` / `getDataUntil($delim)` | バイト列の読み込み |
| `getUIBit` `getUIBits($width)` `getSIBits($width)` | ビット単位の読み込み (MSB から) |
| `getUIBitLSB` `getUIBitsLSB($width)` `getSIBitsLSB($width)` | ビット単位の読み込み (LSB から) |
| `put*` | 上の読み込みに対応する書き込み (64bit と 24bit は無し) |
| `setUI8` `setUI16BE` `setUI32BE` `setUI16LE` `setUI32LE` `setData` `setUIBit` `setUIBits` | 位置を指定した上書き。現在の位置は動かさない。足りない分は NUL で伸ばす |
| `getUIBCD8` / `putUIBCD8` | 1 バイトの BCD (二進化十進数) |
| `need_bits_unsigned($n)` / `need_bits_signed($n)` | 値を表すのに必要なビット数 |
| `hexdump($offset, $length)` | 16 進ダンプの表示 |

- バイト単位の読み書き (`getUI8`, `putData` など) は、始める前にバイト境界に切り上げます。
- 読み込みでデータが足りないときは `IO_Bit_Exception` を投げます。
- バイト単位の `put*` はデータの末尾に追記します。読み込んだデータの途中を書き換えるには `set*` を使います。
- 64bit の読み込みは PHP の整数の範囲で返します。符号無しで 2^63 以上の値は float になり、精度が落ちます。

- `set*` でデータを延長してもカーソルは進みません。延長した部分を `output()` に含めるには、カーソルを新しい末尾に設定してください。

# Test

```
% php tests/run.php
```

# More detail

- https://github.com/yoya/IO_Bit/wiki
