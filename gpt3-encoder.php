<?php
require './vendor/autoload.php';
$encoder = new \GPT3Encoder\Gpt3Encoder();
$prompt = "Many words map to one token, but some don't: indivisible. Unicode characters like emojis may be split into many tokens containing the underlying bytes: 🤚🏾 Sequences of characters commonly found next to each other may be grouped together: 1234567890";
$token_array = $encoder->encode($prompt);
$original_text = $encoder->decode($token_array);

print $original_text . PHP_EOL;