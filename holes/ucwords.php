<?php
return function$helpers() {
    return [
        'constantName' => 'STR',
        'constantValues' => function() use($helpers) {
            $values = ['h e ll o, world!'];
            for ($i = 0; $i < 9; $i++) {
                $values[] = $helpers['randomSentence']);
            }

            return $values;
        },
        'trim' => 'trim',
        'sample' => 'ucwords',
        'disableFunctionality' => ['upper-case-word'],
    ];
};
