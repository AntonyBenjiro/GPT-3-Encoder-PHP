<?php

namespace GPT3Encoder;

interface CacheInterface
{

    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl = 0): void;
}