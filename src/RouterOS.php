<?php

namespace am05mhz;

class RouterOS{
    public $debug     = false;
    private $connected = false;
    private $port      = 8728;
    private $ssl       = false;
    private $timeout   = 3;
    private $attempts  = 5;
    private $delay     = 3;

    private $socket;
    private $error_no;
    private $error_str;
	
    public function __destruct()
    {
        $this->disconnect();
    }

    private function isIterable($var)
    {
        return $var !== null
                and (is_array($var) or $var instanceof Traversable or $var instanceof Iterator or $var instanceof IteratorAggregate);
    }
	
    private function debug($text)
    {
        if ($this->debug) {
            echo $text . "\n";
        }
    }

    private function encodeLength($length)
    {
        if ($length < 0x80) {
            $length = chr($length);
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            $length = chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            $length = chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            $length = chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length >= 0x10000000) {
            $length = chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }

        return $length;
    }

    public function connect($ip, $login, $password)
    {
        for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
            $this->connected = false;
            $proto = ($this->ssl ? 'ssl://' : '' );
            $context = stream_context_create([
					'ssl' => ['ciphers' => 'ADH:ALL', 'verify_peer' => false, 'verify_peer_name' => false]
				]);
            $this->debug('Connection attempt #' . $attempt . ' to ' . $proto . $ip . ':' . $this->port . '...');
            $this->socket = @stream_socket_client($proto . $ip.':'. $this->port, $this->error_no, $this->error_str, $this->timeout, STREAM_CLIENT_CONNECT, $context);
            if ($this->socket) {
                socket_set_timeout($this->socket, $this->timeout);
				$resp = $this->command('/login', false, false);
                if (isset($resp[0]) and $resp[0] == '!done') {
                    $matches = [];
                    if (preg_match_all('/[^=]+/i', $resp[1], $matches)) {
                        if ($matches[0][0] == 'ret' and strlen($matches[0][1]) == 32) {
							$resp = $this->command('/login', ['name' => $login, 'response' => '00' . md5(chr(0) . $password . pack('H*', $matches[0][1]))], false);
                            if (isset($resp[0]) and $resp[0] == '!done') {
                                $this->connected = true;
                                break;
                            }
                        }
                    }
                }
                fclose($this->socket);
            }
            sleep($this->delay);
        }

        if ($this->connected) {
            $this->debug('Connected...');
        } else {
            $this->debug('Error...');
        }
        return $this->connected;
    }

    public function disconnect()
    {
        // let's make sure this socket is still valid.  it may have been closed by something else
        if( is_resource($this->socket) ) {
            fclose($this->socket);
        }
        $this->connected = false;
        $this->debug('Disconnected...');
    }

    private function parseResponse($response)
    {
        if (is_array($response)) {
            $parsed      = array();
            $current     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, array('!fatal','!re','!trap'))) {
                    if ($x == '!re') {
                        $current =& $parsed[];
                    } else {
                        $current =& $parsed[$x][];
                    }
                } elseif ($x != '!done') {
                    $matches = array();
                    if (preg_match_all('/[^=]+/i', $x, $matches)) {
                        if ($matches[0][0] == 'ret') {
                            $singlevalue = $matches[0][1];
                        }
                        $current[$matches[0][0]] = (isset($matches[0][1]) ? $matches[0][1] : '');
                    }
                }
            }

            if (empty($parsed) and !is_null($singlevalue)) {
                $parsed = $singlevalue;
            }

            return $parsed;
        } else {
            return array();
        }
    }

    private function arrayChangeKeyName($array)
    {
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                $tmp = str_replace(['-', '/'], '_', $k);
				$array_new[$tmp] = $v;
            }
            return $array_new;
        } else {
            return $array;
        }
    }

    private function read($parse = true)
    {
        $resp     = array();
        $receiveddone = false;
        while (true) {
            // Read the first byte of input which gives us some or all of the length
            // of the remaining reply.
            $byte   = ord(fread($this->socket, 1));
            $length = 0;
            // If the first bit is set then we need to remove the first four bits, shift left 8
            // and then read another byte in.
            // We repeat this for the second and third bits.
            // If the fourth bit is set, we need to remove anything left in the first byte
            // and then read in yet another byte.
            if ($byte & 128) {
                if (($byte & 192) == 128) {
                    $length = (($byte & 63) << 8) + ord(fread($this->socket, 1));
                } else {
                    if (($byte & 224) == 192) {
                        $length = (($byte & 31) << 8) + ord(fread($this->socket, 1));
                        $length = ($length << 8) + ord(fread($this->socket, 1));
                    } else {
                        if (($byte & 240) == 224) {
                            $length = (($byte & 15) << 8) + ord(fread($this->socket, 1));
                            $length = ($length << 8) + ord(fread($this->socket, 1));
                            $length = ($length << 8) + ord(fread($this->socket, 1));
                        } else {
                            $length = ord(fread($this->socket, 1));
                            $length = ($length << 8) + ord(fread($this->socket, 1));
                            $length = ($length << 8) + ord(fread($this->socket, 1));
                            $length = ($length << 8) + ord(fread($this->socket, 1));
                        }
                    }
                }
            } else {
                $length = $byte;
            }

            $_ = '';

            // If we have got more characters to read, read them in.
            if ($length > 0) {
                $_      = '';
                $retlen = 0;
                while ($retlen < $length) {
                    $toread = $length - $retlen;
                    $_ .= fread($this->socket, $toread);
                    $retlen = strlen($_);
                }
                $resp[] = $_;
                $this->debug('>>> [' . $retlen . '/' . $length . '] bytes read.');
            }

            // If we get a !done, make a note of it.
            if ($_ == '!done') {
                $receiveddone = true;
            }

            $status = socket_get_status($this->socket);
            if ($length > 0) {
                $this->debug('>>> [' . $length . ', ' . $status['unread_bytes'] . ']' . $_);
            }

            if ((!$this->connected and !$status['unread_bytes']) || ($this->connected and !$status['unread_bytes'] and $receiveddone)) {
                break;
            }
        }

        if ($parse) {
            $resp = $this->parseResponse($resp);
        }

        return $resp;
    }

    private function write($command, $continued = true)
    {
        if ($command) {
            $data = explode("\n", $command);
            foreach ($data as $com) {
                $com = trim($com);
                fwrite($this->socket, $this->encodeLength(strlen($com)) . $com);
                $this->debug('<<< [' . strlen($com) . '] ' . $com);
            }

            if (gettype($continued) == 'integer') {
                fwrite($this->socket, $this->encodeLength(strlen('.tag=' . $continued)) . '.tag=' . $continued . chr(0));
                $this->debug('<<< [' . strlen('.tag=' . $continued) . '] .tag=' . $continued);
            } elseif (gettype($continued) == 'boolean') {
                fwrite($this->socket, ($continued ? chr(0) : ''));
            }

            return true;
        } else {
            return false;
        }
    }

    public function command($command, $params = array(), $parseResponse = true)
    {
        $count = count($params);
        $this->write($command, !$params);
        $i = 0;
        if ($this->isIterable($params)){
            foreach($params as $k => $v){
                switch($k[0]){
                    case '?':
                        $el = "$k=$v";
                        break;
                    case '~':
                        $el = "$k~$v";
                        break;
                    default:
                        $el = "=$k=$v";
                        break;
                }

                $last = ($i++ == $count - 1);
                $this->write($el, $last);
            }
        }

        return $this->read($parseResponse);
    }

	private function arrayWhereFilter(Array $filter)
	{
		if (empty($filter)){
			return false;
		}
		$tmp = [];
		foreach($filter as $k => $v){
			switch($k[0]){
				case '?':
				case '~':
					$tmp[$k] = $v;
					break;
				default:
					$tmp['?' . $k] = $v;
					break;
			}
		}
		return $tmp;
	}

	public function getFilterRules(Array $filter = [], $raw = false)
	{
		if (!$this->connected){
			return false;
		}
		$filter = $this->arrayWhereFilter($filter);
		return $this->command('/ip/firewall/filter/print', $filter, !$raw);
	}
	
	public function getAddressLists(Array $filter = [], $raw = false)
	{
		if (!$this->connected){
			return false;
		}
		$filter = $this->arrayWhereFilter($filter);
		return $this->command('/ip/firewall/address-list/print', $filter, !$raw);
	}
	
	public function getNAT(Array $filter = [], $raw = false)
	{
		if (!$this->connected){
			return false;
		}
		$filter = $this->arrayWhereFilter($filter);
		return $this->command('/ip/firewall/nat/print', $filter, !$raw);
	}
	
	public function getMangle(Array $filter = [], $raw = false)
	{
		if (!$this->connected){
			return false;
		}
		$filter = $this->arrayWhereFilter($filter);
		return $this->command('/ip/firewall/mangle/print', $filter, !$raw);
	}
	
	public function getLayer7Protocol(Array $filter = [], $raw = false)
	{
		if (!$this->connected){
			return false;
		}
		$filter = $this->arrayWhereFilter($filter);
		return $this->command('/ip/firewall/layer7-protocol/print', $filter, !$raw);
	}
	
	public function addFilterRule(Array $rule, $raw = false){
		$resp = $this->command('/ip/firewall/filter/add', $rule, !$raw);
		return $resp;
	}
	
	public function addAddressList(Array $rule, $raw = false){
		$resp = $this->command('/ip/firewall/address-list/add', $rule, !$raw);
		if (!$raw){
			if (is_array($resp) and $resp[0] == '!trap'){
				return false;
			}
		}
		return $resp;
	}
	
	public function addNAT(Array $rule, $raw = false){
		$resp = $this->command('/ip/firewall/nat/add', $rule, !$raw);
		return $resp;
	}
	
	public function addMangle(Array $rule, $raw = false){
		$resp = $this->command('/ip/firewall/mangle/add', $rule, !$raw);
		return $resp;
	}
	
	public function addLayer7Protocol(Array $rule, $raw = false){
		$resp = $this->command('/ip/firewall/layer7-protocol/add', $rule, !$raw);
		return $resp;
	}

	public function removeFilterRule(Array $rule, $raw = false){
		$resp = $this->command('/ip/firewall/filter/remove', $rule, !$raw);
		return $resp;
	}
	
	public function removeAddressList(Array $rule, $raw = false){
		$resp = $this->command('/ip/firewall/removeress-list/remove', $rule, !$raw);
		if (!$raw){
			//if (is_array($resp) and $resp[0] == '!trap'){
			//	return false;
			//}
		}
		return $resp;
	}
	
	public function removeNAT(Array $rule, $raw = false){
		$resp = $this->command('/ip/firewall/nat/remove', $rule, !$raw);
		return $resp;
	}
	
	public function removeMangle(Array $rule, $raw = false){
		$resp = $this->command('/ip/firewall/mangle/remove', $rule, !$raw);
		return $resp;
	}
	
	public function removeLayer7Protocol(Array $rule, $raw = false){
		$resp = $this->command('/ip/firewall/layer7-protocol/remove', $rule, !$raw);
		return $resp;
	}
}