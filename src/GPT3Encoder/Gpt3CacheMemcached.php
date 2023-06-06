<?php

namespace GPT3Encoder;

class Gpt3CacheMemcached implements CacheInterface
{

    public function __construct(
        private readonly \Memcached $storage
    )
    {
    }

    public function get(string $key): mixed
    {
        return $this->storage->get($key);
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $expiration = 0;
        if ($ttl > 0) {
            $expiration = time() + $ttl;
        }
        $this->storage->set($key, $value, $expiration);
    }
}