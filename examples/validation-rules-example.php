<?php

/**
 * Validation Rules Usage Examples
 *
 * This file demonstrates how to use validation rules in Toporia Framework.
 * Run with: php examples/validation-rules-example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Validation\Validator;
use Toporia\Framework\Validation\Rules\Required;
use Toporia\Framework\Validation\Rules\Same;

echo "=== Toporia Framework - Validation Rules Examples ===\n\n";

// Example 1: String Rules (Built-in)
echo "1. String Rules (Built-in):\n";
$validator1 = new Validator();
$data1 = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
];
$rules1 = [
    'name' => 'required|string|max:255',
    'email' => 'required|email|max:255',
    'age' => 'required|integer|min:18|max:100',
];

if ($validator1->validate($data1, $rules1)) {
    echo "✓ Validation passed!\n";
    echo "Validated data: " . json_encode($validator1->validated(), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "✗ Validation failed!\n";
    echo "Errors: " . json_encode($validator1->errors(), JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

// Example 2: Rule Objects
echo "2. Rule Objects (Required + Same):\n";
$validator2 = new Validator();
$data2 = [
    'password' => 'secret123',
    'password_confirmation' => 'secret123',
];
$rules2 = [
    'password' => [
        new Required(),
        'string',
        'min:8',
    ],
    'password_confirmation' => [
        new Required(),
        new Same('password'),
    ],
];

if ($validator2->validate($data2, $rules2)) {
    echo "✓ Validation passed!\n";
} else {
    echo "✗ Validation failed!\n";
    echo "Errors: " . json_encode($validator2->errors(), JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

// Example 3: Mixed String Rules and Rule Objects
echo "3. Mixed String Rules and Rule Objects:\n";
$validator3 = new Validator();
$data3 = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret123',
    'password_confirmation' => 'secret123',
];
$rules3 = [
    'name' => [
        new Required(),
        'string',
        'max:255',
    ],
    'email' => 'required|email|max:255',
    'password' => [
        'required',
        'string',
        'min:8',
        'max:255',
    ],
    'password_confirmation' => [
        'required',
        new Same('password'),
    ],
];

if ($validator3->validate($data3, $rules3)) {
    echo "✓ Validation passed!\n";
    echo "Validated data: " . json_encode($validator3->validated(), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "✗ Validation failed!\n";
    echo "Errors: " . json_encode($validator3->errors(), JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

// Example 4: Validation Failure
echo "4. Validation Failure Example:\n";
$validator4 = new Validator();
$data4 = [
    'name' => '', // Empty
    'email' => 'invalid-email', // Invalid format
    'password' => 'short', // Too short
    'password_confirmation' => 'different', // Doesn't match
];
$rules4 = [
    'name' => [
        new Required(),
        'string',
        'max:255',
    ],
    'email' => 'required|email|max:255',
    'password' => [
        'required',
        'string',
        'min:8',
    ],
    'password_confirmation' => [
        'required',
        new Same('password'),
    ],
];

if ($validator4->validate($data4, $rules4)) {
    echo "✓ Validation passed!\n";
} else {
    echo "✗ Validation failed!\n";
    echo "Errors:\n";
    foreach ($validator4->errors() as $field => $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo "  - {$field}: {$error}\n";
        }
    }
}
echo "\n";

// Example 5: Custom Error Messages
echo "5. Custom Error Messages:\n";
$validator5 = new Validator();
$data5 = ['email' => 'invalid'];
$rules5 = ['email' => 'required|email'];
$messages5 = [
    'email.required' => 'Email là bắt buộc',
    'email.email' => 'Email không hợp lệ',
];

if ($validator5->validate($data5, $rules5, $messages5)) {
    echo "✓ Validation passed!\n";
} else {
    echo "✗ Validation failed!\n";
    echo "Errors: " . json_encode($validator5->errors(), JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

echo "=== Examples Complete ===\n";

