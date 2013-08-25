<?php
return function() {
    $randomWord = function() {
        $word = '';
        $length = rand(1, 20);
        for ($i = 0; $i < $length; $i++) {
            $word .= chr(rand(97, 122));
        }

        return $word;
    };

    return [
        'randomWord' => $randomWord,
        'randomSentence' => function() use ($randomWord) {
            $words = [];
            $length = rand(5, 20);
            for ($i = 0; $i < $length; $i++) {
                $word = $randomWord();
                if (rand(0, 5) === 0) {
                    $word .= ',';
                }

                $words[] = $word;
            }

            $punctuation = '.!?';
            return implode(' ', $words) . $punctuation[rand(0, strlen($punctuation) - 1)];
        },
    ]
};
