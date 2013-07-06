<?php
return [
    'trim' => 'trim',
    'sample' => function() {
        $result = '';
        for ($x = 99; $x > 0;) {
            $b = "{$x} bottle" . ($x > 1 ? 's' : '');
            $result .= "{$b} of beer on the wall, {$b} of beer.\n";
            $x--;
            if ($x > 0) {
                $result .= "Take one down and pass it around, {$x} bottle" . ($x > 1 ? 's' : '') . " of beer on the wall.\n\n";
            } else {
                $result .= 'Go to the store and buy some more, 99 bottles of beer on the wall.';
            }
        }

        return $result;
    },
];
