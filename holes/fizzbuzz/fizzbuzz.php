<?php
for ($i = 1; $i <= NUM; $i++) {
    if ($i % 15 == 0)
        echo "FizzBuzz\n";
    elseif ($i % 3 == 0)
        echo "Fizz\n";
    elseif ($i % 5 == 0)
        echo "Buzz\n";
    else
        echo "$i\n";
}
