<?php
return [
    'tagName' => 'php-5.4',
    'addConstant' => function($script, $constantName, $constantValue) {
        if (is_string($constantValue)) {
            $constantValue = "'{$constantValue}'";
        }

        return <<<EOD
<?php
define('{$constantName}', {$constantValue});
?>
{$script}
EOD;
    },
    'executeCommand' => '/usr/bin/php',
    'disableFunctionality' => function(array $baseImage, $functionality) {
        $disableFunctions = [];
        switch ($functionality) {
            case 'upper-case-word':
                $disableFunctions = ['ucwords', 'ucfirst', 'mb_convert_case'];
                break;
        }

        if (empty($disableFunctions)) {
            return $baseImage;
        }

        return \Bizgolf\buildImage(
            $baseImage,
            [],
            ["RUN sed -i '/^disable_functions/s/$/," . implode(',', $disableFunctions) . "/' /etc/php/php.ini"]
        );
    },
];
