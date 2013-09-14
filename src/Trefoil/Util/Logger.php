<?php
namespace Trefoil\Util;

class Logger
{
    const LOGGER_DEBUG = 0;
    const LOGGER_INFO = 1;
    const LOGGER_NOTICE = 2;
    const LOGGER_WARNING = 3;
    const LOGGER_ERROR = 4;

    protected $logFile;

    public function __construct($logFile)
    {
        $this->logFile = $logFile;
    }

    public function init()
    {
        file_put_contents($this->logFile, '');
    }

    public function log($level, $message, $context = null, $object = null)
    {
        $t = microtime(true);
        $micro = sprintf("%06d",($t - floor($t)) * 1000000);
        $d = new \DateTime( date('Y-m-d H:i:s.'.$micro,$t) );

        $line = sprintf('%s %s:%s:%s %s', $d->format('Ymd His.u'), $level, $object, $context, $message);

        file_put_contents($this->logFile, $line."\n", FILE_APPEND);
    }

    public function info($message, $context = null, $object = null)
    {
        $this->log(self::LOGGER_INFO, $message, $context, $object);
    }

    public function error($message, $context = null, $object = null)
    {
        $this->log(self::LOGGER_ERROR, $message, $context, $object);
    }

    public function notice($message, $context = null, $object = null)
    {
        $this->log(self::LOGGER_NOTICE, $message, $context, $object);
    }

    public function warning($message, $context = null, $object = null)
    {
        $this->log(self::LOGGER_WARNING, $message, $context, $object);
    }

    public function debug($message, $context = null, $object = null)
    {
        $this->log(self::LOGGER_DEBUG, $message, $context, $object);
    }



}
