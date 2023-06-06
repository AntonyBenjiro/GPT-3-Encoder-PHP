<?php

namespace GPT3Encoder;

class Gpt3Encoder
{

    private readonly bool $mbStringLoaded;
    private int $memoryLimit;
    private readonly CacheInterface $cache;
    private readonly int $memoryLimitThreshold;

    public function __construct(
        private readonly Gpt3EncoderConfiguration $configuration = new Gpt3EncoderConfiguration
    )
    {
        $cacheClass = $this->configuration->getCacheClass();
        $this->memoryLimitThreshold = $this->configuration->getMemoryLimitThreshold();
        $this->cache = new $cacheClass;
        $this->mbStringLoaded = function_exists('mb_strlen');
        $memory_limit = ini_get('memory_limit');
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            if ($matches[2] === 'M') {
                $memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
            } else if ($matches[2] === 'K') {
                $memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
            }
        }
        $this->memoryLimit = $memory_limit;
    }


    /**
     * @param string $text
     * @return array
     * @throws \JsonException
     */
    public function encode(string $text): array
    {
        $bpe_tokens = [];
        if (empty($text)) {
            return $bpe_tokens;
        }
        $this->memcheck();
        $byte_encoder = $this->byteEncoder();
        $this->memcheck();
        $encoder = $this->encoder();
        $this->memcheck();
        $bpe_file = $this->vocabulary();
        $this->memcheck();

        preg_match_all("#'s|'t|'re|'ve|'m|'ll|'d| ?\p{L}+| ?\p{N}+| ?[^\s\p{L}\p{N}]+|\s+(?!\S)|\s+#u", $text, $matches);
        if (!isset($matches[0]) || count($matches[0]) === 0) {
            throw new \RuntimeException('Failed to match string');
        }
        $lines = preg_split('/\r\n|\r|\n/', $bpe_file);
        $bpe_merges = [];
        $bpe_merges_temp = array_slice($lines, 1, count($lines), true);
        foreach ($bpe_merges_temp as $bmt) {
            $split_bmt = preg_split('#(\s+)#', $bmt);
            $split_bmt = array_filter($split_bmt, fn($item) => $this->myFilter($item));
            $this->memcheck();
            if (count($split_bmt) > 0) {
                $bpe_merges[] = $split_bmt;
            }
        }
        $bpe_ranks = $this->dictZip($bpe_merges);

        $cache = [];
        foreach ($matches[0] as $token) {
            $new_tokens = [];
            $chars = [];
            $this->memcheck();
            $token = $this->utf8Encode($token);
            if ($this->mbStringLoaded) {
                $len = mb_strlen($token, 'UTF-8');
                for ($i = 0; $i < $len; $i++) {
                    $this->memcheck();
                    $chars[] = mb_substr($token, $i, 1, 'UTF-8');
                }
            } else {
                $chars = str_split($token);
            }
            $result_word = '';
            foreach ($chars as $char) {
                $this->memcheck();
                if (isset($byte_encoder[$this->unichr($char)])) {
                    $result_word .= $byte_encoder[$this->unichr($char)];
                }
            }
            $this->memcheck();
            $new_tokens_bpe = $this->bpe($result_word, $bpe_ranks, $cache);
            $this->memcheck();
            $new_tokens_bpe = explode(' ', $new_tokens_bpe);
            foreach ($new_tokens_bpe as $x) {
                $this->memcheck();
                if (isset($encoder[$x])) {
                    if (isset($new_tokens[$x])) {
                        $new_tokens[rand() . '---' . $x] = $encoder[$x];
                    } else {
                        $new_tokens[$x] = $encoder[$x];
                    }
                } else
                    if (isset($new_tokens[$x])) {
                        $new_tokens[rand() . '---' . $x] = $x;
                    } else {
                        $new_tokens[$x] = $x;
                    }
            }
            foreach ($new_tokens as $index => $val) {
                $this->memcheck();
                if (isset($bpe_tokens[$index])) {
                    $bpe_tokens[rand() . '---' . $index] = $val;
                } else {
                    $bpe_tokens[$index] = $val;
                }
            }
        }
        return $bpe_tokens;
    }

    private function memcheck(): void
    {
        if (memory_get_usage(true) > $this->memoryLimit - $this->memoryLimitThreshold) {
            throw new \RuntimeException(
                'Memory limit exhausted. Consider increase memory_limit for script to run'
            );
        }
    }

    /**
     * @throws \JsonException
     */
    private function byteEncoder(): array
    {
        if (!$encoder = $this->cache->get('byteEncoder')) {
            $encoder = json_decode(
                file_get_contents(
                    $this->configuration->getCharLibPath()
                ), true, 512, JSON_THROW_ON_ERROR
            );
            $this->cache->set('byteEncoder', $encoder);
        }
        return $encoder;
    }

    /**
     * @throws \JsonException
     */
    private function encoder(): array
    {
        if (!$encoder = $this->cache->get('encoder')) {
            $encoder = json_decode(
                file_get_contents(
                    $this->configuration->getEncoderLibPath()
                ),
                true, 512, JSON_THROW_ON_ERROR
            );
            $this->cache->set('encoder', $encoder);
        }
        return $encoder;
    }

    private function vocabulary(): string
    {
        if (!$vocabulary = $this->cache->get('vocabulary')) {
            $vocabulary = file_get_contents($this->configuration->getVocabularyPath());
            $this->cache->set('vocabulary', $vocabulary);
        }
        return $vocabulary;
    }

    private function myFilter($var): bool
    {
        return ($var !== NULL && $var !== FALSE && $var !== '');
    }

    private function dictZip(array $x): array
    {
        $result = [];
        $cnt = 0;
        foreach ($x as $i) {
            $this->memcheck();
            if (isset($i[1], $i[0])) {
                $result[$i[0] . ',' . $i[1]] = $cnt;
                $cnt++;
            }
        }
        return $result;

    }

    private function utf8Encode(string $str): string
    {
        $str .= $str;
        $len = strlen($str);
        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            $this->memcheck();
            switch (true) {
                case $str[$i] < "\x80":
                    $str[$j] = $str[$i];
                break;
                case $str[$i] < "\xC0":
                    $str[$j] = "\xC2";
                    $str[++$j] = $str[$i];
                break;
                default:
                    $str[$j] = "\xC3";
                    $str[++$j] = chr(ord($str[$i]) - 64);
                break;
            }
        }
        return substr($str, 0, $j);
    }

    private function unichr($c): float|int
    {
        if (ord($c[0]) >= 0 && ord($c[0]) <= 127) {
            return ord($c[0]);
        }
        if (ord($c[0]) >= 192 && ord($c[0]) <= 223) {
            return (ord($c[0]) - 192) * 64 + (ord($c[1]) - 128);
        }
        if (ord($c[0]) >= 224 && ord($c[0]) <= 239) {
            return (ord($c[0]) - 224) * 4096 + (ord($c[1]) - 128) * 64 + (ord($c[2]) - 128);
        }
        if (ord($c[0]) >= 240 && ord($c[0]) <= 247) {
            return (ord($c[0]) - 240) * 262144 + (ord($c[1]) - 128) * 4096 + (ord($c[2]) - 128) * 64 + (ord($c[3]) - 128);
        }
        if (ord($c[0]) >= 248 && ord($c[0]) <= 251) {
            return (ord($c[0]) - 248) * 16777216 + (ord($c[1]) - 128) * 262144 + (ord($c[2]) - 128) * 4096 + (ord($c[3]) - 128) * 64 + (ord($c[4]) - 128);
        }
        if (ord($c[0]) >= 252 && ord($c[0]) <= 253) {
            return (ord($c[0]) - 252) * 1073741824 + (ord($c[1]) - 128) * 16777216 + (ord($c[2]) - 128) * 262144 + (ord($c[3]) - 128) * 4096 + (ord($c[4]) - 128) * 64 + (ord($c[5]) - 128);
        }
        return 0;
    }

    private function bpe(string $token, array $bpe_ranks, array &$cache)
    {
        if (array_key_exists($token, $cache)) {
            return $cache[$token];
        }
        $word = $this->split($token);
        $init_len = count($word);
        $pairs = $this->getPairs($word);
        if (!$pairs) {
            return $token;
        }
        while (true) {
            $minPairs = [];
            $this->memcheck();
            foreach ($pairs as $pair) {
                $this->memcheck();
                if (array_key_exists($pair[0] . ',' . $pair[1], $bpe_ranks)) {
                    $rank = $bpe_ranks[$pair[0] . ',' . $pair[1]];
                    $minPairs[$rank] = $pair;
                } else {
                    $minPairs[10e10] = $pair;
                }
            }
            ksort($minPairs);
            $min_key = array_key_first($minPairs);
            foreach ($minPairs as $mpi => $mp) {
                $this->memcheck();
                if ($mpi < $min_key) {
                    $min_key = $mpi;
                }
            }
            $bigram = $minPairs[$min_key];
            if (!array_key_exists($bigram[0] . ',' . $bigram[1], $bpe_ranks)) {
                break;
            }
            $first = $bigram[0];
            $second = $bigram[1];
            $new_word = [];
            $i = 0;
            while ($i < count($word)) {
                $this->memcheck();
                $j = $this->indexOf($word, $first, $i);
                if ($j === -1) {
                    $new_word = array_merge($new_word, array_slice($word, $i, null, true));
                    break;
                }
                if ($i > $j) {
                    $slicer = [];
                } elseif ($j == 0) {
                    $slicer = [];
                } else {
                    $slicer = array_slice($word, $i, $j - $i, true);
                }
                $new_word = array_merge($new_word, $slicer);
                if (count($new_word) > $init_len) {
                    break;
                }
                $i = $j;
                if ($word[$i] === $first && $i < count($word) - 1 && $word[$i + 1] === $second) {
                    $new_word[] = $first . $second;
                    $i += 2;
                } else {
                    $new_word[] = $word[$i];
                    ++$i;
                }
            }
            if ($word == $new_word) {
                break;
            }
            $word = $new_word;
            if (count($word) === 1) {
                break;
            }

            $pairs = $this->getPairs($word);
        }
        $word = implode(' ', $word);
        $cache[$token] = $word;
        return $word;
    }

    private function split(string $str): array
    {
        $arr = [];
        if ($this->mbStringLoaded) {
            $length = mb_strlen($str, 'UTF-8');
        } else {
            $length = strlen($str);
        }

        for ($i = 0; $i < $length; $i++) {
            if ($this->mbStringLoaded) {
                $arr[] = mb_substr($str, $i, 1, 'UTF-8');
            } else {
                $arr[] = $str[$i];
            }
        }
        return $arr;
    }

    private function getPairs($word): array
    {
        $pairs = [];
        $prev_char = $word[0];
        for ($i = 1, $iMax = count($word); $i < $iMax; $i++) {
            $char = $word[$i];
            $pairs[] = array($prev_char, $char);
            $prev_char = $char;
        }
        return $pairs;
    }

    private function indexOf(array $array, $searchElement, int $fromIndex): int
    {
        foreach ($array as $index => $value) {
            if ($index < $fromIndex) {
                continue;
            }
            if ($value == $searchElement) {
                return $index;
            }
        }
        return -1;
    }

    /**
     * @throws \JsonException
     */
    public function decode(array $tokens, bool $throwOnUndefinedChar = false): string
    {
        $this->memcheck();
        $decoder = array_flip($this->encoder());
        $byte_decoder = array_flip($this->byteEncoder());
        $text = '';
        foreach ($tokens as $myt) {
            if (!isset($decoder[$myt]) && $throwOnUndefinedChar) {
                throw new \RuntimeException('Character not found in decoder: ' . $myt);
            }
            $text .= $decoder[$myt] ?? '';
        }
        $text_arr = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $final_arr = [];
        foreach ($text_arr as $txa) {
            $this->memcheck();
            if (!isset($byte_decoder[$txa]) && $throwOnUndefinedChar) {
                throw new \RuntimeException('Character not found in byte_decoder: ' . $txa);
            }
            $final_arr[] = $byte_decoder[$txa] ?? '';
        }
        $output = '';
        foreach ($final_arr as $iValue) {
            $output .= chr($iValue);
        }
        return $output;
    }
}