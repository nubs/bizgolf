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
        for ($i = 1; $i <= $num; $i++) {
            if ($i % 15 == 0)
                echo "FizzBuzz\n";
            elseif ($i % 3 == 0)
                echo "Fizz\n";
            elseif ($i % 5 == 0)
                echo "Buzz\n";
            else
                echo "$i\n";
        }
    },
];
