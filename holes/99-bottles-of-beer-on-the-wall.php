<?php
namespace Codegolf;

return [
    'validate' => function($image) {
        $holeDir = __DIR__ . '/99-bottles-of-beer-on-the-wall';

        $result = execute($image);

        return $result['exitStatus'] === 0 && trim($result['output']) === file_get_contents("{$holeDir}/output.txt");
    },
];
