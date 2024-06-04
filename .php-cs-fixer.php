<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        // PHP >= 7.1 required for const
        'visibility_required' => ['property', 'method'],
        ])
    ->setFinder($finder)
;
