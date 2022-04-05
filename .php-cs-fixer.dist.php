<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->path('/^src\//')
    ->path('/^bin\//')
    ->files()
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
$config
    ->setRules([
        '@Symfony' => true,
        'concat_space' => ['spacing' => 'one'],
        'no_spaces_around_offset' => ['positions' => ['outside']], // we prefer spaces around method calls inside []
        'normalize_index_brace' => false, // prefer {} when accessing single characters in strings $var{0}
        'phpdoc_separation' => false,
        'phpdoc_no_empty_return' => false,
        'phpdoc_summary' => false, // we don't care about periods
        'phpdoc_var_without_name'=> false,
        'phpdoc_to_comment' => false, // this will prevent screwing with lambda comments
        'increment_style' => false,
        'standardize_increment' => false,
        'yoda_style' => false,
        'phpdoc_types_order' => ['null_adjustment' => 'none', 'sort_algorithm' => 'none'],
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
    ;

return $config;
