<?php

/**
 * PHP CS Fixer Configuration for CodeIgniter 4 Chat Application
 *
 * This configuration enforces PSR-12 coding standards with some
 * sensible additions for modern PHP development.
 *
 * Run check:  composer cs-check
 * Run fix:    composer cs-fix
 *
 * For more info: https://cs.symfony.com/
 */

$finder = PhpCsFixer\Finder::create()
    // Directories to scan
    ->in([
        __DIR__ . '/app',
    ])
    // Exclude paths that shouldn't be auto-formatted
    ->exclude([
        // Views contain mixed PHP/HTML - manual formatting preferred
        'Views',
        // These are auto-generated or framework-provided
        'ThirdParty',
    ])
    // Only process PHP files
    ->name('*.php')
    // Skip hidden directories
    ->ignoreDotFiles(true)
    // Skip VCS directories
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)  // Allow rules that might change code behavior
    ->setRules([
        // -----------------------------------------------------------------
        // PSR-12 as our base standard
        // This is the modern PHP coding standard that CodeIgniter 4 follows
        // -----------------------------------------------------------------
        '@PSR12' => true,

        // -----------------------------------------------------------------
        // PHP Version Targeting
        // Ensures syntax is compatible with PHP 8.4
        // -----------------------------------------------------------------
        '@PHP84Migration' => true,

        // -----------------------------------------------------------------
        // Array Syntax
        // Use short array syntax: [] instead of array()
        // -----------------------------------------------------------------
        'array_syntax' => ['syntax' => 'short'],

        // -----------------------------------------------------------------
        // Imports & Use Statements
        // -----------------------------------------------------------------
        // Sort use statements alphabetically
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        // Remove unused use statements
        'no_unused_imports' => true,
        // Single import per use statement (easier to read diffs)
        'single_import_per_statement' => true,

        // -----------------------------------------------------------------
        // Whitespace & Formatting
        // -----------------------------------------------------------------
        // No whitespace before semicolons
        'no_whitespace_before_comma_in_array' => true,
        // Add space after type colon in return types
        'return_type_declaration' => ['space_before' => 'none'],
        // Blank line before namespace is handled by PSR-12
        // 'single_blank_line_before_namespace' is deprecated - use blank_lines_before_namespace instead
        // No extra blank lines
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
            ],
        ],
        // Trim trailing whitespace in comments
        'no_trailing_whitespace_in_comment' => true,

        // -----------------------------------------------------------------
        // String Handling
        // -----------------------------------------------------------------
        // Use single quotes when possible (no variable interpolation)
        'single_quote' => true,
        // Ensure proper escaping in strings
        'escape_implicit_backslashes' => true,

        // -----------------------------------------------------------------
        // Operators
        // -----------------------------------------------------------------
        // Use !== and === instead of != and ==
        'strict_comparison' => false,  // Disabled - can change behavior
        // Standardize concatenation spacing
        'concat_space' => ['spacing' => 'one'],

        // -----------------------------------------------------------------
        // Type Declarations
        // -----------------------------------------------------------------
        // Ensure void return type is declared where applicable
        'void_return' => false,  // Disabled - risky for existing code
        // Compact nullable type declarations: ?string instead of string|null
        'nullable_type_declaration_for_default_null_value' => true,

        // -----------------------------------------------------------------
        // Comments & Documentation
        // -----------------------------------------------------------------
        // Align phpdoc tags vertically
        'phpdoc_align' => ['align' => 'vertical'],
        // Remove @return void if function has no return
        'phpdoc_no_useless_inheritdoc' => true,
        // Scalar types in PHPDoc should be lowercase
        'phpdoc_scalar' => true,
        // Fix PHPDoc types
        'phpdoc_types' => true,
        // Separate different PHPDoc tag groups with blank line
        'phpdoc_separation' => true,
        // Trim whitespace in PHPDoc
        'phpdoc_trim' => true,
        // Add missing @param and @return annotations
        'phpdoc_add_missing_param_annotation' => false,  // Disabled - noisy

        // -----------------------------------------------------------------
        // Class & Method Formatting
        // -----------------------------------------------------------------
        // Order class elements consistently
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
        // Ensure visibility is declared for methods and properties
        'visibility_required' => [
            'elements' => ['method', 'property', 'const'],
        ],

        // -----------------------------------------------------------------
        // Control Structures
        // -----------------------------------------------------------------
        // Use Yoda conditions: if (null === $var) - DISABLED
        // Most devs find standard conditions more readable
        'yoda_style' => false,
        // Add trailing comma in multiline arrays (helps with diffs)
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays'],
        ],
        // Use null coalescing operator where possible
        'ternary_to_null_coalescing' => true,

        // -----------------------------------------------------------------
        // Clean Code
        // -----------------------------------------------------------------
        // Remove unused variables - DISABLED (risky)
        // 'no_unused_imports' is enabled above
        // Remove short echo tags <?= (keep for views)
        'echo_tag_syntax' => false,
    ])
    ->setFinder($finder)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
