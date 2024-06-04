<?php

/**
 *        MIT License
 *
 *        Copyright (c) 2022-2024 SQLite Cloud, Inc.
 *
 *        Permission is hereby granted, free of charge, to any person obtaining a copy
 *        of this software and associated documentation files (the "Software"), to deal
 *        in the Software without restriction, including without limitation the rights
 *        to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *        copies of the Software, and to permit persons to whom the Software is
 *        furnished to do so, subject to the following conditions:
 *
 *        The above copyright notice and this permission notice shall be included in all
 *        copies or substantial portions of the Software.
 *
 *        THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *        IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *        FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *        AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *        LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *        OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *        SOFTWARE.
 */

// v1.1.0: added new rowset metadata v2
//         removed ACK for rowset sent in chunk

namespace SQLiteCloud\SQLiteCloud;

const CMD_STRING = '+';
const CMD_ZEROSTRING = '!';
const CMD_ERROR = '-';
const CMD_INT = ':';
const CMD_FLOAT = ',';
const CMD_ROWSET = '*';
const CMD_ROWSET_CHUNK = '/';
const CMD_JSON = '#';
const CMD_RAWJSON = '{';
const CMD_NULL = '_';
const CMD_BLOB = '$';
const CMD_COMPRESSED = '%';
const CMD_PUBSUB = '|';
const CMD_COMMAND = '^';
const CMD_RECONNECT = '@';
const CMD_ARRAY = '=';

const ROWSET_CHUNKS_END = '/6 0 0 0 ';

class SQLiteCloudClient
{
    const SDKVERSION = '1.1.0';

    // User name is required unless connectionstring is provided
    public $username = '';
    // Password is required unless connection string is provided
    public $password = '';
    // Password is hashed
    public $password_hashed = false;
    // API key instead of username and password
    public $apikey = '';

    // Name of database to open
    public $database = '';
    // Optional query timeout passed directly to TLS socket
    public $timeout = null;
    // Socket connection timeout
    public $connect_timeout = 20;

    // Enable compression
    public $compression = false;
    // Tell the server to zero-terminate strings
    public $zerotext = false;
    // Database will be created in memory
    public $memory = false;
    // Create the database if it doesn't exist?
    public $create = false;
    // Request for immediate responses from the server node without waiting for linerizability guarantees
    public $non_linearizable = false;
    // Connect using plain TCP port, without TLS encryption, NOT RECOMMENDED
    public $insecure = false;
    // Accept invalid TLS certificates
    public $no_verify_certificate = false;

    // Certificates
    public $tls_root_certificate = null;
    public $tls_certificate = null;
    public $tls_certificate_key = null;

    // Server should send BLOB columns
    public $noblob = false;
    // Do not send columns with more than max_data bytes
    public $maxdata = 0;
    // Server should chunk responses with more than maxRows
    public $maxrows = 0;
    // Server should limit total number of rows in a set to maxRowset
    public $maxrowset = 0;

    public $errmsg = null;
    public $errcode = 0;
    public $xerrcode = 0;

    private $socket = null;
    private $isblob = false;
    private $rowset = null;

    // PUBLIC
    public function connect($hostname = "localhost", $port = 8860)
    {
        $ctx = ($this->insecure) ? 'tcp' : 'tls';
        $address = "{$ctx}://{$hostname}:{$port}";

        // check setup context for TLS connection
        $context = null;
        if (!$this->insecure) {
            $context = stream_context_create();
            if ($this->tls_root_certificate) {
                stream_context_set_option($context, 'ssl', 'cafile', $this->tls_root_certificate);
            }
            if ($this->tls_certificate) {
                stream_context_set_option($context, 'ssl', 'local_cert', $this->tls_certificate);
            }
            if ($this->tls_certificate_key) {
                stream_context_set_option($context, 'ssl', 'local_pk', $this->tls_certificate_key);
            }
            if ($this->no_verify_certificate) {
                stream_context_set_option($context, 'ssl', 'verify_peer ', false);
                stream_context_set_option($context, 'ssl', 'verify_peer_name ', false);
            }
        }

        // connect to remote socket
        $socket = stream_socket_client(
            $address,
            $this->errcode,
            $this->errmsg,
            $this->connect_timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$socket) {
            if ($this->errcode == 0) {
                // if the value returned in errcode is 0 and stream_socket_client returned false, it is an indication
                // that the error occurred before the connect() call. This is most likely due to a problem initializing
                // the socket
                $extmsg = ($this->insecure) ? '(before connecting to remote host)' : '(possibly wrong TLS certificate)';
                $this->errmsg = "An error occurred while initializing the socket {$extmsg}.";
                $this->errcode = -1;
            }
            return false;
        }

        $this->socket = $socket;
        if ($this->internalConfigApply() == false) {
            return false;
        }

        return true;
    }

    public function connectWithString($connectionString)
    {
        // URL STRING FORMAT
        // sqlitecloud://user:pass@host.com:port/dbname?timeout=10&key2=value2&key3=value3
        // or sqlitecloud://host.sqlite.cloud:8860/dbname?apikey=zIiAARzKm9XBVllbAzkB1wqrgijJ3Gx0X5z1A4m4xBA

        $params = parse_url($connectionString);
        if (!is_array($params)) {
            $this->errmsg = "Invalid connection string: {$connectionString}.";
            $this->errcode = -1;
            return false;
        }

        $options = [];
        $query = $params['query'] ?? '';
        parse_str($query, $options);
        foreach ($options as $option => $value) {
            $opt = strtolower($option);

            // prefix for certificate options
            if (
                $opt === "root_certificate"
                || $opt === "certificate"
                || $opt === "certificate_key"
            ) {
                $opt = "tls_" . $opt;
            }

            // alias
            if ($opt === "nonlinearizable") {
                $opt = "non_linearizable";
            }

            if (property_exists($this, $opt)) {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null) {
                    $this->{$opt} = (bool) ($value);
                } elseif (is_numeric($value)) {
                    $this->{$opt} = (int) ($value);
                } else {
                    $this->{$opt} = $value;
                }
            }
        }

        // apikey or username/password is accepted
        if (!$this->apikey) {
            $this->username = isset($params['user']) ? urldecode($params['user']) : '';
            $this->password = isset($params['pass']) ? urldecode($params['pass']) : '';
        }

        $path = $params['path'] ?? '';
        $database = str_replace('/', '', $path);
        if ($database) {
            $this->database = $database;
        }

        $hostname = $params['host'];
        $port = isset($params['port']) ? ($params['port']) : null;

        if ($port) {
            return $this->connect($hostname, $port);
        }

        return $this->connect($hostname);
    }

    public function disconnect()
    {
        $this->internalClearError();
        if ($this->socket) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    public function execute($command)
    {
        return $this->internalRunCommand($command);
    }

    public function sendblob($blob)
    {
        $this->isblob = true;
        $rc = $this->internalRunCommand($blob);
        $this->isblob = false;
        return $rc;
    }

    // MARK: -

    // PRIVATE

    // lz4decode function from http://heap.ch/blog/2019/05/18/lz4-decompression/
    /*
        MIT License

        Copyright (c) 2019 Stephan J. Müller

        Permission is hereby granted, free of charge, to any person obtaining a copy
        of this software and associated documentation files (the "Software"), to deal
        in the Software without restriction, including without limitation the rights
        to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
        copies of the Software, and to permit persons to whom the Software is
        furnished to do so, subject to the following conditions:

        The above copyright notice and this permission notice shall be included in all
        copies or substantial portions of the Software.

        THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
        IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
        FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
        AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
        LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
        OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
        SOFTWARE.
    */
    private function lz4decode($in, $offset = 0, $header = '')
    {
        $len = strlen($in);
        $out = $header;
        $i = $offset;
        $take = function () use ($in, &$i) {
            return ord($in[$i++]);
        };
        $addOverflow = function (&$sum) use ($take) {
            do {
                $sum += $summand = $take();
            } while ($summand === 0xFF);
        };
        while ($i < $len) {
            $token = $take();
            $nLiterals = $token >> 4;
            if ($nLiterals === 0xF) {
                $addOverflow($nLiterals);
            }
            $out .= substr($in, $i, $nLiterals);
            $i += $nLiterals;
            if ($i === $len) {
                break;
            }
            $offset = $take() | $take() << 8;
            $matchlength = $token & 0xF;
            if ($matchlength === 0xF) {
                $addOverflow($matchlength);
            }
            $matchlength += 4;
            $j = strlen($out) - $offset;
            while ($matchlength--) {
                $out .= $out[$j++];
            }
        }
        return $out;
    }

    private function internalConfigApply()
    {
        if ($this->timeout > 0) {
            stream_set_timeout($this->socket, $this->timeout);
        }

        $buffer = '';

        // it must be executed before authentication command
        if ($this->non_linearizable) {
            $buffer .= "SET CLIENT KEY NONLINEARIZABLE TO 1;";
        }

        if ($this->apikey) {
            $buffer .= "AUTH APIKEY {$this->apikey};";
        }

        if ($this->username && $this->password) {
            $command = $this->password_hashed ? 'HASH' : 'PASSWORD';
            $buffer .= "AUTH USER {$this->username} {$command} {$this->password};";
        }

        if ($this->database) {
            if ($this->create && !$this->memory) {
                $buffer .= "CREATE DATABASE {$this->database} IF NOT EXISTS;";
            }
            $buffer .= "USE DATABASE {$this->database};";
        }

        if ($this->compression) {
            $buffer .= "SET CLIENT KEY COMPRESSION TO 1;";
        }

        if ($this->zerotext) {
            $buffer .= "SET CLIENT KEY ZEROTEXT TO 1;";
        }

        if ($this->noblob) {
            $buffer .= "SET CLIENT KEY NOBLOB TO 1;";
        }

        if ($this->maxdata) {
            $buffer .= "SET CLIENT KEY MAXDATA TO {$this->maxdata};";
        }

        if ($this->maxrows) {
            $buffer .= "SET CLIENT KEY MAXROWS TO {$this->maxrows};";
        }

        if ($this->maxrowset) {
            $buffer .= "SET CLIENT KEY MAXROWSET TO {$this->maxrowset};";
        }

        if (strlen($buffer) > 0) {
            $result = $this->internalRunCommand($buffer);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    private function internalRunCommand($buffer)
    {
        $this->internalClearError();

        if ($this->internalSocketWrite($buffer) === false) {
            return false;
        }
        return $this->internalSocketRead();
    }

    private function internalSetupPubsub($buffer)
    {
        return true;
    }

    private function internalReconnect($buffer)
    {
        return true;
    }

    private function internalParseArray($buffer)
    {
        // extract the number of values in the array
        $start = 0;
        $n = $this->internalParseNumber($buffer, $start, $unused, 0);

        // loop to parse each individual value
        $r = [];
        for ($i = 0; $i < $n; ++$i) {
            $cellsize = 0;
            $len = strlen($buffer) - $start;
            $value = $this->internalParseValue($buffer, $len, $cellsize, $start);
            $start += $cellsize;
            array_push($r, $value);
        }

        return $r;
    }

    private function internalClearError()
    {
        $this->errmsg = null;
        $this->errcode = 0;
        $this->xerrcode = 0;
    }

    private function internalSocketWrite($buffer)
    {
        // compute header
        $delimit = ($this->isblob) ? '$' : '+';
        $len = ($buffer) ? strlen($buffer) : 0;
        $header = "{$delimit}{$len} ";

        // write header and buffer
        if (fwrite($this->socket, $header) === false) {
            return false;
        }
        if ($len == 0) {
            return true;
        }
        if (fwrite($this->socket, $buffer) === false) {
            return false;
        }

        return true;
    }

    private function internalSocketRead()
    {
        $buffer = "";
        $len = 8192;

        $nread = 0;
        while (true) {
            // read from socket
            $temp = fread($this->socket, $len);
            if ($temp === false) {
                return false;
            }

            // update buffers
            $buffer .= $temp;
            $nread += strlen($temp);

            // get first character
            $c = $buffer[0];

            // check if command does not have an explicit length
            if (($c == CMD_INT) || ($c == CMD_FLOAT) || ($c == CMD_NULL)) {
                // command is terminated by a space character
                if ($buffer[$nread - 1] != ' ') {
                    continue;
                }
            } elseif ($c == CMD_ROWSET_CHUNK) {
                // chunkes are completed when the buffer contains the end-of-chunk marker
                $isEndOfChunk = substr($buffer, -strlen(ROWSET_CHUNKS_END)) == ROWSET_CHUNKS_END;
                if (!$isEndOfChunk) {
                    continue;
                }
            } else {
                $cstart = 0;
                $n = $this->internalParseNumber($buffer, $cstart);

                $can_be_zerolength = ($c == CMD_BLOB) || ($c == CMD_STRING);
                if ($n == 0 && !$can_be_zerolength) {
                    continue;
                }

                // check exit condition
                if ($n + $cstart != $nread) {
                    continue;
                }
            }

            return $this->internalParseBuffer($buffer, $nread);
        }

        return false;
    }

    private function internalUncompressData($buffer)
    {
        // %LEN COMPRESSED UNCOMPRESSED BUFFER

        // extract compressed size
        $space_index = strpos($buffer, ' ');
        $buffer = substr($buffer, $space_index + 1);

        // extract compressed size
        $space_index = strpos($buffer, ' ');
        $compressed_size = intval(substr($buffer, 0, $space_index));
        $buffer = substr($buffer, $space_index + 1);

        // extract decompressed size
        $space_index = strpos($buffer, ' ');
        $uncompressed_size = intval(substr($buffer, 0, $space_index));
        $buffer = substr($buffer, $space_index + 1);

        // extract data header
        $header = substr($buffer, 0, -$compressed_size);

        // extract compressed data
        $compressed_buffer = substr($buffer, -$compressed_size);

        $decompressed_buffer = $header . $this->lz4decode($compressed_buffer, 0);

        // sanity check result
        if (strlen($decompressed_buffer) != $uncompressed_size + strlen($header)) {
            return null;
        }

        return $decompressed_buffer;
    }

    private function internalParseValue($buffer, &$len, &$cellsize = null, $index = 0)
    {
        if ($len <= 0) {
            return null;
        }

        // handle special NULL value case
        if (is_null($buffer) || $buffer[$index] == CMD_NULL) {
            $len = 0;
            if (!is_null($cellsize)) {
                $cellsize = 2;
            }
            return null;
        }

        $cstart = $index;
        $blen = $this->internalParseNumber($buffer, $cstart, $unused, $index + 1);

        // handle decimal/float cases
        if (($buffer[$index] == CMD_INT) || ($buffer[$index] == CMD_FLOAT)) {
            $nlen = $cstart - $index;
            $len = $nlen - 2;
            if (!is_null($cellsize)) {
                $cellsize = $nlen;
            }
            return substr($buffer, $index + 1, $len);
        }

        $len = ($buffer[$index] == CMD_ZEROSTRING) ? $blen - 1 : $blen;
        if (!is_null($cellsize)) {
            $cellsize = $blen + $cstart - $index;
        }

        return substr($buffer, $cstart, $len);
    }

    private function internalParseBuffer($buffer, $blen)
    {
        // possible return values:
        // true => OK
        // false => error
        // integer
        // double
        // string
        // array
        // object
        // NULL

        // check OK value
        if (strcmp($buffer, '+2 OK') == 0) {
            return true;
        }

        // check for compressed result
        if ($buffer[0] == CMD_COMPRESSED) {
            $buffer = $this->internalUncompressData($buffer);
            if ($buffer == null) {
                $this->errcode = -1;
                $this->errmsg = "An error occurred while decompressing the input buffer of len {$blen}.";
                return false;
            }
            // after decompression length has changed
            $blen = strlen($buffer);
        }

        // first character contains command type
        switch ($buffer[0]) {
            case CMD_ZEROSTRING:
            case CMD_RECONNECT:
            case CMD_PUBSUB:
            case CMD_COMMAND:
            case CMD_STRING:
            case CMD_ARRAY:
            case CMD_BLOB:
            case CMD_JSON:
                $cstart = 0;
                $len = $this->internalParseNumber($buffer, $cstart);
                if ($len == 0) {
                    return "";
                }

                if ($buffer[0] == CMD_ZEROSTRING) {
                    --$len;
                }
                $clone = substr($buffer, $cstart, $len);

                if ($buffer[0] == CMD_COMMAND) {
                    return $this->internalRunCommand($clone);
                } elseif ($buffer[0] == CMD_PUBSUB) {
                    return $this->internalSetupPubsub($clone);
                } elseif ($buffer[0] == CMD_RECONNECT) {
                    return $this->internalReconnect($clone);
                } elseif ($buffer[0] == CMD_ARRAY) {
                    return $this->internalParseArray($clone);
                }

                return $clone;

            case CMD_ERROR:
                // -LEN ERRCODE:EXTCODE ERRMSG
                $cstart = 0;
                $cstart2 = 0;
                $len = $this->internalParseNumber($buffer, $cstart);
                $clone = substr($buffer, $cstart);

                $extcode = 0;
                $errcode = $this->internalParseNumber($clone, $cstart2, $extcode, 0);
                $this->errcode = $errcode;
                $this->xerrcode = $extcode;

                $len -= $cstart2;
                $this->errmsg = substr($clone, $cstart2);

                return false;

            case CMD_ROWSET:
            case CMD_ROWSET_CHUNK:
                // CMD_ROWSET:          *LEN 0:VERSION ROWS COLS DATA
                // - When decompressed, LEN for ROWSET is *0
                //
                // CMD_ROWSET_CHUNK:    /LEN IDX:VERSION ROWS COLS DATA
                //
                $start = $this->internalParseRowsetSignature($buffer, $len, $idx, $version, $nrows, $ncols);
                if ($start < 0) {
                    return false;
                }

                // check for end-of-chunk condition
                if ($start == 0 && $version == 0) {
                    $rowset = $this->rowset;
                    $this->rowset = null;
                    return $rowset;
                }

                $rowset = $this->internalParseRowset($buffer, $start, $idx, $version, $nrows, $ncols);

                // continue parsing next chunk in the buffer
                if ($buffer[0] == CMD_ROWSET_CHUNK) {
                    $buffer = substr($buffer, $len + strlen("/{$len} "));
                    if ($buffer) {
                        return $this->internalParseBuffer($buffer, strlen($buffer));
                    }
                }

                return $rowset;

            case CMD_NULL:
                return null;

            case CMD_INT:
            case CMD_FLOAT:
                $clone = $this->internalParseValue($buffer, $blen);
                if (is_null($clone)) {
                    return 0;
                }
                if ($buffer[0] == CMD_INT) {
                    return intval($clone);
                }
                return floatval($clone);

            case CMD_RAWJSON:
                return null;
        }

        return null;
    }

    private function internalParseNumber($buffer, &$cstart, &$extcode = null, $index = 1)
    {
        $value = 0;
        $extvalue = 0;
        $isext = false;
        $blen = strlen($buffer);

        // from 1 to skip the first command type character
        for ($i = $index; $i < $blen; $i++) {
            $c = $buffer[$i];

            // check for optional extended error code (ERRCODE:EXTERRCODE)
            if ($c == ':') {
                $isext = true;
                continue;
            }

            // check for end of value
            if ($c == ' ') {
                $cstart = $i + 1;
                if (!is_null($extcode)) {
                    $extcode = $extvalue;
                }
                return $value;
            }

            // compute numeric value
            if ($isext) {
                $extvalue = ($extvalue * 10) + ((int)$buffer[$i]);
            } else {
                $value = ($value * 10) + ((int)$buffer[$i]);
            }
        }

        return 0;
    }

    // MARK: -

    public function internalParseRowsetSignature($buffer, &$len, &$idx, &$version, &$nrows, &$ncols)
    {
        // ROWSET:          *LEN 0:VERS NROWS NCOLS DATA
        // ROWSET in CHUNK: /LEN IDX:VERS NROWS NCOLS DATA

        // check for end-of-chunk condition
        if ($buffer == ROWSET_CHUNKS_END) {
            $version = 0;
            return 0;
        }

        $start = 1;
        $counter = 0;
        $n = strlen($buffer);
        for ($i = 0; $i < $n; $i++) {
            if ($buffer[$i] != ' ') {
                continue;
            }
            ++$counter;

            $data = substr($buffer, $start, $i - $start);
            $start = $i + 1;

            if ($counter == 1) {
                $len = intval($data);
            } elseif ($counter == 2) {
                // idx:vers
                $values = explode(":", $data);
                $idx = intval($values[0]);
                $version = intval($values[1]);
            } elseif ($counter == 3) {
                $nrows = intval($data);
            } elseif ($counter == 4) {
                $ncols = intval($data);
                return $start;
            } else {
                return -1;
            }
        }
        return -1;
    }

    public function internalParseRowsetHeader($rowset, $buffer, $start)
    {
        $ncols = $rowset->ncols;

        // parse column names (header is guarantee to contain column names)
        $rowset->colname = [];
        for ($i = 0; $i < $ncols; $i++) {
            $len = $this->internalParseNumber($buffer, $cstart, $unused, $start);
            $value = substr($buffer, $cstart, $len);
            array_push($rowset->colname, $value);
            $start = $cstart + $len;
        }

        if ($rowset->version == 1) {
            return $start;
        }

        // if version != 2 returns an error because rowset version is not supported
        if ($rowset->version != 2) {
            return -1;
        }

        // parse declared types
        $rowset->decltype = [];
        for ($i = 0; $i < $ncols; $i++) {
            $len = $this->internalParseNumber($buffer, $cstart, $unused, $start);
            $value = substr($buffer, $cstart, $len);
            array_push($rowset->decltype, $value);
            $start = $cstart + $len;
        }

        // parse database names
        $rowset->dbname = [];
        for ($i = 0; $i < $ncols; $i++) {
            $len = $this->internalParseNumber($buffer, $cstart, $unused, $start);
            $value = substr($buffer, $cstart, $len);
            array_push($rowset->dbname, $value);
            $start = $cstart + $len;
        }

        // parse table names
        $rowset->tblname = [];
        for ($i = 0; $i < $ncols; $i++) {
            $len = $this->internalParseNumber($buffer, $cstart, $unused, $start);
            $value = substr($buffer, $cstart, $len);
            array_push($rowset->tblname, $value);
            $start = $cstart + $len;
        }

        // parse column original names
        $rowset->origname = [];
        for ($i = 0; $i < $ncols; $i++) {
            $len = $this->internalParseNumber($buffer, $cstart, $unused, $start);
            $value = substr($buffer, $cstart, $len);
            array_push($rowset->origname, $value);
            $start = $cstart + $len;
        }

        // parse not null flags
        $rowset->notnull = [];
        for ($i = 0; $i < $ncols; $i++) {
            $value = $this->internalParseNumber($buffer, $cstart, $unused, $start);
            array_push($rowset->notnull, $value);
            $start = $cstart;
        }

        // parse primary key flags
        $rowset->prikey = [];
        for ($i = 0; $i < $ncols; $i++) {
            $value = $this->internalParseNumber($buffer, $cstart, $unused, $start);
            array_push($rowset->prikey, $value);
            $start = $cstart;
        }

        // parse autoincrement flags
        $rowset->autoinc = [];
        for ($i = 0; $i < $ncols; $i++) {
            $value = $this->internalParseNumber($buffer, $cstart, $unused, $start);
            array_push($rowset->autoinc, $value);
            $start = $cstart;
        }

        return $start;
    }

    public function internalParseRowsetValues($rowset, $buffer, $start, $bound)
    {
        // loop to parse each individual value
        for ($i = 0; $i < $bound; ++$i) {
            $cellsize = 0;
            $len = strlen($buffer) - $start;
            $value = $this->internalParseValue($buffer, $len, $cellsize, $start);
            $start += $cellsize;
            array_push($rowset->data, $value);
        }
    }

    public function internalParseRowset($buffer, $start, $idx, $version, $nrows, $ncols)
    {
        $rowset = null;
        $n = $start;
        $ischunk = ($buffer[0] == CMD_ROWSET_CHUNK);

        // idx == 0 means first (and only) chunk for rowset
        // idx == 1 means first chunk for chunked rowset
        $first_chunk = ($ischunk) ? ($idx == 1) : ($idx == 0);
        if ($first_chunk) {
            $rowset = new SQLiteCloudRowset();
            $rowset->nrows = $nrows;
            $rowset->ncols = $ncols;
            $rowset->version = $version;
            $rowset->data = [];
            if ($ischunk) {
                $this->rowset = $rowset;
            }
            $n = $this->internalParseRowsetHeader($rowset, $buffer, $start);
            if ($n <= 0) {
                return null;
            }
        } else {
            $rowset = $this->rowset;
            $rowset->nrows += $nrows;
        }

        // parse values
        $this->internalParseRowsetValues($rowset, $buffer, $n, $nrows * $ncols);

        return $rowset;
    }

    // MARK: -

    public function __destruct()
    {
        $this->disconnect();
    }
}
