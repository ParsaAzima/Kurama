<?php

declare(strict_types=1);

namespace Kurama;

/** Minimal file logger with PSR-3-style level methods. No PSR dependency. */
final class Logger
{
    private const LEVELS = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    public function __construct(
        private readonly string $path,
        private readonly string $level = 'debug',
        private readonly int $maxBytes = 0,
    ) {
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        if (!isset(self::LEVELS[$level]) || !isset(self::LEVELS[$this->level])) {
            return;
        }
        if (self::LEVELS[$level] > self::LEVELS[$this->level]) {
            return;
        }

        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if ($this->maxBytes > 0 && is_file($this->path) && filesize($this->path) >= $this->maxBytes) {
            @rename($this->path, $this->path . '.1');
        }

        $line = sprintf('[%s] %s %s', date('Y-m-d H:i:s'), strtoupper($level), $message);
        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        @file_put_contents($this->path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
