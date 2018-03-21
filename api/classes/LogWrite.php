<?php

class LogWrite
{
	private $_logFile = 'log.txt';

	public function __construct($logFileName = null) {
		if ($logFileName) $this->_logFile = $logFileName;
	}

	public function logWriter($message){
		file_put_contents($this->_logFile, '['.date("H:m:s d.m.y"). "] ".$message."\r\n", FILE_APPEND | LOCK_EX);
	}
}