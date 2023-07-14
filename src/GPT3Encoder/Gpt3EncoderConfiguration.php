<?php

namespace GPT3Encoder;

class Gpt3EncoderConfiguration
{

    private string $charLibPath = __DIR__ . '/characters.json';
    private string $encoderLibPath = __DIR__ . '/encoder.json';
    private string $vocabularyPath = __DIR__ . '/vocab.bpe';
    private string $cache = Gpt3CacheArray::class;

    public function setCharacters(string $path): static
    {
        $this->charLibPath = $path;
        return $this;
    }

    public function setEncoder(string $path): static
    {
        $this->encoderLibPath = $path;
        return $this;
    }

    public function setVocabulary(string $path): static
    {
        $this->vocabularyPath = $path;
        return $this;
    }

    /**
     * @return string
     */
    public function getVocabularyPath(): string
    {
        return $this->vocabularyPath;
    }

    /**
     * @return string
     */
    public function getEncoderLibPath(): string
    {
        return $this->encoderLibPath;
    }

    /**
     * @return string
     */
    public function getCharLibPath(): string
    {
        return $this->charLibPath;
    }

    /**
     * @return string
     */
    public function getCacheClass(): string
    {
        return $this->cache;
    }

    /**
     * @param class-string|CacheInterface $cache
     * @return Gpt3EncoderConfiguration
     */
    public function setCacheClass(string $cache): static
    {
        $this->cache = $cache;
        return $this;
    }
}