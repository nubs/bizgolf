<?php
return function($image) {
    $holeDir = __DIR__;

    $correctImage = createImage('php-5.5', "{$holeDir}/fizzbuzz.php");

    $verify = function($num) use ($image, $correctImage) {
        $result = execute($image, "NUM={$num}");
        $correctResult = execute($correctImage, "NUM={$num}");

        return $result['exitStatus'] === 0 && rtrim($result['output']) === rtrim($correctResult['output']);
    };

    $values = [100, 1000];
    for ($i = 0; $i < 8; $i++) {
        $values[] = rand(101, 999);
    }

    foreach ($values as $value) {
        if (!$verify($value)) {
            return false;
        }
    }

    return true;
};
