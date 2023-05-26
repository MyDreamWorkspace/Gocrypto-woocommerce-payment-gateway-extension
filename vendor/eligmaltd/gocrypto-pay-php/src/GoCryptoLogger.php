<?php
// Author (c) Daniel Jaušovec, Eligma Ltd.
// Version: EP-001:2022-07-07

namespace Eligmaltd\GoCryptoPayPHP;

class GoCryptoLogger {
	private $logFile;

	public function __construct() {
		$this->logFile = dirname(__FILE__) . '/logs/pay.log';
	}

	public function writeLog($s) {
		$line = '*** ' . gmdate('r') . ' ' . $s . '\n';
		file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
	}
}
