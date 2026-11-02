<?php

namespace Zca;

use Psr\Log\LoggerInterface;

/**
 * Port of `logger(ctx)` from zca-js.
 *
 * Messages are printed to stdout (like console.log) when `logging` is enabled,
 * or forwarded to a PSR-3 logger when the `logger` option is set.
 */
class Logger
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private array $options) {}

    public static function of(Context|array $ctx): self
    {
        return new self($ctx instanceof Context ? $ctx->options : ($ctx["options"] ?? $ctx));
    }

    public function verbose(mixed ...$args): void
    {
        $this->write("debug", "\x1b[35m🚀 VERBOSE\x1b[0m", $args);
    }

    public function info(mixed ...$args): void
    {
        $this->write("info", "\x1b[34mINFO\x1b[0m", $args);
    }

    public function warn(mixed ...$args): void
    {
        $this->write("warning", "\x1b[33mWARN\x1b[0m", $args);
    }

    public function error(mixed ...$args): void
    {
        $this->write("error", "\x1b[31mERROR\x1b[0m", $args);
    }

    public function success(mixed ...$args): void
    {
        $this->write("info", "\x1b[32mSUCCESS\x1b[0m", $args);
    }

    public function timestamp(mixed ...$args): void
    {
        $now = gmdate("Y-m-d\TH:i:s") . sprintf(".%03dZ", Utils::now() % 1000);
        $this->write("info", "\x1b[90m[{$now}]\x1b[0m", $args);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function write(string $level, string $prefix, array $args): void
    {
        if (empty($this->options["logging"])) return;

        $message = implode(" ", array_map([self::class, "stringify"], $args));

        $logger = $this->options["logger"] ?? null;
        if ($logger instanceof LoggerInterface) {
            $logger->log($level, "[zca] " . $message);
            return;
        }

        echo $prefix . ($message === "" ? "" : " " . $message) . PHP_EOL;
    }

    private static function stringify(mixed $arg): string
    {
        if (is_string($arg)) return $arg;
        if ($arg instanceof \Throwable) return get_class($arg) . ": " . $arg->getMessage();
        if (is_scalar($arg) || $arg === null) return var_export($arg, true);

        return json_encode($arg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: print_r($arg, true);
    }
}
