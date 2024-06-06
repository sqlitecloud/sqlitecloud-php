<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PHP70Migration' => true,
        '@PSR12' => true,
        // PHP >= 7.1 required for const
        'visibility_required' => [
            'elements' => ['property', 'method']
        ],
    ])
    ->setFinder($finder)
;
