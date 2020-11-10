<?php
declare(strict_types=1);

/*
 * (c) 2020 Georg Großberger <contact@grossberger-ge.org>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the Apache License 2.0
 *
 * For the full copyright and license information see
 * <https://www.apache.org/licenses/LICENSE-2.0>
 */

use GrossbergerGeorg\PHPDevTools\Fixer\LowerHeaderCommentFixer;
use GrossbergerGeorg\PHPDevTools\Fixer\NamespaceFirstFixer;
use GrossbergerGeorg\PHPDevTools\Fixer\SingleEmptyLineFixer;
use Symfony\Component\Finder\Finder;

$header = '(c) ' . date('Y') . ' Georg Großberger <contact@grossberger-ge.org>

This file is free software; you can redistribute it and/or
modify it under the terms of the Apache License 2.0

For the full copyright and license information see
<https://www.apache.org/licenses/LICENSE-2.0>';

LowerHeaderCommentFixer::setHeader($header);

$finder = Finder::create()
    ->name('/\\.php$/')
    ->in(__DIR__)
    ->notPath('vendor/')
    ->notPath('bin/');

return PhpCsFixer\Config::create()
    ->setUsingCache(true)
    ->registerCustomFixers([
        new LowerHeaderCommentFixer(),
        new NamespaceFirstFixer(),
        new SingleEmptyLineFixer(),
    ])
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2'                                 => true,
        '@DoctrineAnnotation'                   => true,
        'declare_strict_types'                  => true,
        'GrossbergerGeorg/lower_header_comment' => true,
        'GrossbergerGeorg/namespace_first'      => true,
        'GrossbergerGeorg/single_empty_line'    => true,
        'align_multiline_comment'               => [
            'comment_type' => 'phpdocs_like',
        ],
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'binary_operator_spaces' => [
            'default'   => 'single_space',
            'operators' => [
                '=>' => 'align_single_space_minimal',
            ],
        ],
        'blank_line_before_statement' => [
            'statements' => ['if', 'try', 'return'],
        ],
        'cast_spaces' => [
            'space' => 'single',
        ],
        'doctrine_annotation_array_assignment' => [
            'operator' => '=',
        ],
        'ereg_to_preg'                => true,
        'escape_implicit_backslashes' => [
            'double_quoted'  => true,
            'heredoc_syntax' => true,
            'single_quoted'  => true,
        ],
        'explicit_indirect_variable'         => true,
        'explicit_string_variable'           => true,
        'function_typehint_space'            => true,
        'linebreak_after_opening_tag'        => true,
        'lowercase_cast'                     => true,
        'magic_constant_casing'              => true,
        'modernize_types_casting'            => true,
        'native_function_casing'             => true,
        'new_with_braces'                    => true,
        'no_alias_functions'                 => true,
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc'        => true,
        'no_empty_comment'                   => true,
        'no_empty_phpdoc'                    => true,
        'no_empty_statement'                 => true,
        'no_leading_import_slash'            => true,
        'no_mixed_echo_print'                => [
            'use' => 'echo',
        ],
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_null_property_initialization'             => true,
        'no_php4_constructor'                         => true,
        'no_short_bool_cast'                          => true,
        'no_singleline_whitespace_before_semicolons'  => true,
        'no_spaces_after_function_name'               => true,
        'no_spaces_around_offset'                     => [
            'positions' => ['inside', 'outside'],
        ],
        'no_spaces_inside_parenthesis'          => true,
        'no_superfluous_elseif'                 => true,
        'no_trailing_comma_in_singleline_array' => true,
        'no_unused_imports'                     => true,
        'no_useless_return'                     => true,
        'no_whitespace_in_blank_line'           => true,
        'non_printable_character'               => true,
        'ordered_class_elements'                => true,
        'ordered_imports'                       => true,
        'phpdoc_indent'                         => true,
        'phpdoc_scalar'                         => true,
        'short_scalar_cast'                     => true,
        'single_line_comment_style'             => true,
        'single_quote'                          => true,
        'standardize_not_equals'                => true,
        'ternary_operator_spaces'               => true,
        'visibility_required'                   => true,
        'whitespace_after_comma_in_array'       => true,
    ])
    ->setFinder($finder);
