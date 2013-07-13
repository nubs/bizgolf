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
    }
];
