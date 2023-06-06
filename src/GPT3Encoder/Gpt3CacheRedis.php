<?php

namespace GPT3Encoder;

class Gpt3CacheRedis implements CacheInterface
{
    public function __construct(
        private readonly \Redis $storage
    )
    {
    }

    public function get(string $key): mixed
    {
        return $this->storage->get($key);
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $this->storage->set($key, $value, $ttl > 0 ? $ttl : null);
    }
}