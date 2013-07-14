<?php
$randomWord = function() {
    $word = '';
    $length = rand(1, 20);
    for ($i = 0; $i < $length; $i++) {
        $word .= chr(rand(97, 122));
    }

    return $word;
};

$randomSentence = function() use ($randomWord) {
    $words = [];
    $length = rand(5, 20);
    for ($i = 0; $i < $length; $i++) {
        $word = $randomWord();
        if (rand(0, 5) === ',') {
            $word .= ',';
        }

        $words[] = $word;
    }

    $punctuation = '.!?';
    return implode(' ', $words) . $punctuation[rand(0, strlen($punctuation) - 1)];
};

return [
    'constantName' => 'STR',
    'constantValues' => function() use($randomSentence) {
        $values = ['h e ll o, world!'];
        for ($i = 0; $i < 9; $i++) {
            $values[] = $randomSentence();
        }

        return $values;
    },
    'trim' => 'trim',
    'sample' => 'ucwords',
    'disableFunctionality' => ['upper-case-word'],
];
