<?php
namespace Codegolf;

return [
    'constantName' => 'NUM',
    'constantValues' => function() {
        $values = [100, 1000];
        for ($i = 0; $i < 8; $i++) {
            $values[] = rand(101, 999);
        }

        return $values;
    },
    'trim' => 'rtrim',
    'sample' => function($num) {
        $result = '';
        for ($i = 1; $i <= $num; $i++) {
            if ($i % 15 == 0) {
                $result .= "FizzBuzz\n";
            } elseif ($i % 3 == 0) {
                $result .= "Fizz\n";
            } elseif ($i % 5 == 0) {
                $result .= "Buzz\n";
            } else {
                $result .= "$i\n";
            }
        }

        return $result;
    },
];
