<?php

/**
 * Array Validation Examples
 *
 * Demonstrates array validation features in Toporia Framework.
 * Run with: php examples/validation-array-example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Validation\Validator;
use Toporia\Framework\Validation\Rules\ArrayDistinct;
use Toporia\Framework\Validation\Rules\ArrayMin;
use Toporia\Framework\Validation\Rules\ArrayMax;

echo "=== Toporia Framework - Array Validation Examples ===\n\n";

// Example 1: Basic Array Validation
echo "1. Basic Array Validation:\n";
$validator1 = new Validator();
$data1 = [
    'tags' => ['php', 'laravel', 'framework'],
    'items' => [1, 2, 3, 4, 5],
];
$rules1 = [
    'tags' => 'required|array|min:1|max:10',
    'items' => 'required|array|min:2|max:5',
];

if ($validator1->validate($data1, $rules1)) {
    echo "✓ Validation passed!\n";
} else {
    echo "✗ Validation failed!\n";
    print_r($validator1->errors());
}
echo "\n";

// Example 2: Wildcard Notation
echo "2. Wildcard Notation (items.*.name):\n";
$validator2 = new Validator();
$data2 = [
    'items' => [
        ['name' => 'Item 1', 'price' => 100],
        ['name' => 'Item 2', 'price' => 200],
        ['name' => '', 'price' => 300], // Empty name - should fail
    ],
];
$rules2 = [
    'items.*.name' => 'required|string|max:255',
    'items.*.price' => 'required|numeric|min:0',
];

if ($validator2->validate($data2, $rules2)) {
    echo "✓ Validation passed!\n";
} else {
    echo "✗ Validation failed!\n";
    foreach ($validator2->errors() as $field => $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo "  - {$field}: {$error}\n";
        }
    }
}
echo "\n";

// Example 3: Array Element Validation
echo "3. Array Element Validation (emails.*):\n";
$validator3 = new Validator();
$data3 = [
    'emails' => [
        'john@example.com',
        'jane@example.com',
        'invalid-email', // Invalid - should fail
    ],
];
$rules3 = [
    'emails' => 'required|array|min:1',
    'emails.*' => 'required|email',
];

if ($validator3->validate($data3, $rules3)) {
    echo "✓ Validation passed!\n";
} else {
    echo "✗ Validation failed!\n";
    foreach ($validator3->errors() as $field => $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo "  - {$field}: {$error}\n";
        }
    }
}
echo "\n";

// Example 4: Array Distinct
echo "4. Array Distinct (Unique Values):\n";
$validator4 = new Validator();
$data4 = [
    'tags' => ['php', 'laravel', 'php', 'framework'], // Duplicate 'php'
];
$rules4 = [
    'tags' => [
        'required',
        'array',
        new ArrayDistinct(),
    ],
];

if ($validator4->validate($data4, $rules4)) {
    echo "✓ Validation passed!\n";
} else {
    echo "✗ Validation failed!\n";
    foreach ($validator4->errors() as $field => $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo "  - {$field}: {$error}\n";
        }
    }
}
echo "\n";

// Example 5: Nested Array Validation
echo "5. Nested Array Validation:\n";
$validator5 = new Validator();
$data5 = [
    'products' => [
        [
            'name' => 'Product 1',
            'tags' => ['electronics', 'smartphone'],
        ],
        [
            'name' => 'Product 2',
            'tags' => ['electronics', 'tablet'],
        ],
    ],
];
$rules5 = [
    'products' => 'required|array|min:1',
    'products.*.name' => 'required|string|max:255',
    'products.*.tags' => 'required|array|min:1',
    'products.*.tags.*' => 'required|string|max:50',
];

if ($validator5->validate($data5, $rules5)) {
    echo "✓ Validation passed!\n";
} else {
    echo "✗ Validation failed!\n";
    foreach ($validator5->errors() as $field => $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo "  - {$field}: {$error}\n";
        }
    }
}
echo "\n";

// Example 6: Array Size Validation
echo "6. Array Size Validation:\n";
$validator6 = new Validator();
$data6 = [
    'items' => [1, 2, 3], // 3 items
];
$rules6 = [
    'items' => 'required|array:2,5', // Must have 2-5 items
];

if ($validator6->validate($data6, $rules6)) {
    echo "✓ Validation passed!\n";
} else {
    echo "✗ Validation failed!\n";
    print_r($validator6->errors());
}
echo "\n";

echo "=== Examples Complete ===\n";

