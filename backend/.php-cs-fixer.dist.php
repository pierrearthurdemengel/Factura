<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'no_superfluous_phpdoc_tags' => true,
        'concat_space' => ['spacing' => 'one'],
        'phpdoc_to_comment' => ['allow_before_return_statement' => true],
    ])
    ->setFinder($finder)
;
