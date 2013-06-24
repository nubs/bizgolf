<?php
return function($image) {
    $holeDir = __DIR__;

    $result = execute($image);

    return $result['exitStatus'] === 0 && trim($result['output']) === file_get_contents("{$holeDir}/output.txt");
};
