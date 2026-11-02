<?php

namespace Zca;

/**
 * Map of upload callbacks with a time to live (default 5 minutes).
 */
class CallbacksMap
{
    public const FIVE_MINUTES = 5 * 60 * 1000;

    /** @var array<string, array{callback: callable, expiresAt: int}> */
    private $items = [];

    /**
     * @param int $ttl Time to live in milliseconds. Default is 5 minutes.
     * @return static
     */
    public function set(string $key, callable $value, int $ttl = self::FIVE_MINUTES)
    {
        $this->purge();
        $this->items[$key] = [
            "callback" => $value,
            "expiresAt" => Utils::now() + $ttl,
        ];

        return $this;
    }

    public function get(string $key): ?callable
    {
        $this->purge();

        return isset($this->items[$key]) ? $this->items[$key]["callback"] : null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        $exists = isset($this->items[$key]);
        unset($this->items[$key]);

        return $exists;
    }

    private function purge(): void
    {
        $now = Utils::now();
        foreach ($this->items as $key => $item) {
            if ($item["expiresAt"] <= $now) unset($this->items[$key]);
        }
    }
}
