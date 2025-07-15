<?php declare(strict_types=1);

namespace Trefoil\Helpers;

use Psr\Log\LogLevel;

class MemoryLogger extends \Psr\Log\AbstractLogger
{
    /**
     * Special minimum log level which will not log any log levels.
     */
    public const LOG_LEVEL_NONE = 'none';

    /**
     * Log level hierarchy
     */
    public const LEVELS = [
        self::LOG_LEVEL_NONE => -1,
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    private $messages = [];
    private $logLevel = LogLevel::DEBUG;

    public function __construct(string $logLevel = LogLevel::DEBUG)
    {
        $this->setLogLevel($logLevel);
    }

    public function setLogLevel(string $logLevel): void
    {
        if (!isset(self::LEVELS[$logLevel])) {
            throw new \InvalidArgumentException(sprintf('Invalid log level "%s".', $logLevel));
        }
        $this->logLevel = $logLevel;
    }

    public function log($level, $message, array $context = []): void
    {
        if (self::LEVELS[$level] >= self::LEVELS[$this->logLevel]) {
            $this->messages[] = sprintf('[%s] %s', strtoupper($level), $message);
        }
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}