<?php
namespace Codegolf;

return [
    'trim' => 'trim',
    'sample' => function() {
        for ($x = 99; $x > 0;) {
            $b = "{$x} bottle" . ($x > 1 ? 's' : '');
            echo "{$b} of beer on the wall, {$b} of beer.\n";
            $x--;
            if ($x > 0) {
                echo "Take one down and pass it around, {$x} bottle" . ($x > 1 ? 's' : '') . " of beer on the wall.\n\n";
            } else {
                echo 'Go to the store and buy some more, 99 bottles of beer on the wall.';
            }
        }
    },
];
