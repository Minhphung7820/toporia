<?php

/**
 * Unique and Exists Validation Examples
 *
 * Demonstrates:
 * - Unique validation with single ignore condition
 * - Unique validation with multiple ignore conditions
 * - Exists validation with additional conditions
 * - Array validation with unique/exists rules
 * - Using Rule objects vs string rules
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Toporia\Framework\Validation\Validator;
use Toporia\Framework\Validation\Rules\Unique;
use Toporia\Framework\Validation\Rules\Exists;

// Example 1: Simple unique validation
echo "=== Example 1: Simple Unique ===\n";
$validator1 = new Validator(
    ['email' => 'test@example.com'],
    ['email' => 'unique:users,email']
);
$result1 = $validator1->validate();
echo "Valid: " . ($result1 ? "Yes" : "No") . "\n";
if (!$result1) {
    print_r($validator1->errors());
}

// Example 2: Unique with single ignore condition (backward compatible)
echo "\n=== Example 2: Unique with Single Ignore (Backward Compatible) ===\n";
$validator2 = new Validator(
    ['email' => 'test@example.com', 'id' => 1],
    ['email' => 'unique:users,email,1,id'] // Ignore id=1
);
$result2 = $validator2->validate();
echo "Valid: " . ($result2 ? "Yes" : "No") . "\n";

// Example 3: Unique with multiple ignore conditions (new format)
echo "\n=== Example 3: Unique with Multiple Ignores ===\n";
$validator3 = new Validator(
    ['email' => 'test@example.com', 'id' => 1, 'status' => 'deleted'],
    ['email' => 'unique:users,email,id:1,status:deleted'] // Ignore id=1 AND status=deleted
);
$result3 = $validator3->validate();
echo "Valid: " . ($result3 ? "Yes" : "No") . "\n";

// Example 4: Unique with Rule object
echo "\n=== Example 4: Unique Rule Object ===\n";
$validator4 = new Validator(
    ['email' => 'test@example.com', 'id' => 1],
    ['email' => [new Unique('users', 'email', ['id' => 1])]]
);
$result4 = $validator4->validate();
echo "Valid: " . ($result4 ? "Yes" : "No") . "\n";

// Example 5: Unique Rule Object with multiple ignores
echo "\n=== Example 5: Unique Rule Object with Multiple Ignores ===\n";
$validator5 = new Validator(
    ['email' => 'test@example.com', 'id' => 1, 'name' => 'John'],
    ['email' => [new Unique('users', 'email', ['id' => 1, 'name' => 'John'])]]
);
$result5 = $validator5->validate();
echo "Valid: " . ($result5 ? "Yes" : "No") . "\n";

// Example 6: Simple exists validation
echo "\n=== Example 6: Simple Exists ===\n";
$validator6 = new Validator(
    ['category_id' => 5],
    ['category_id' => 'exists:categories,id']
);
$result6 = $validator6->validate();
echo "Valid: " . ($result6 ? "Yes" : "No") . "\n";

// Example 7: Exists with additional conditions
echo "\n=== Example 7: Exists with Conditions ===\n";
$validator7 = new Validator(
    ['category_id' => 5],
    ['category_id' => 'exists:categories,id,status:active'] // Must exist AND status=active
);
$result7 = $validator7->validate();
echo "Valid: " . ($result7 ? "Yes" : "No") . "\n";

// Example 8: Exists with multiple conditions
echo "\n=== Example 8: Exists with Multiple Conditions ===\n";
$validator8 = new Validator(
    ['user_id' => 10],
    ['user_id' => 'exists:users,id,status:active,deleted_at:null'] // Must exist AND status=active AND deleted_at IS NULL
);
$result8 = $validator8->validate();
echo "Valid: " . ($result8 ? "Yes" : "No") . "\n";

// Example 9: Exists Rule Object
echo "\n=== Example 9: Exists Rule Object ===\n";
$validator9 = new Validator(
    ['category_id' => 5],
    ['category_id' => [new Exists('categories', 'id', ['status' => 'active'])]]
);
$result9 = $validator9->validate();
echo "Valid: " . ($result9 ? "Yes" : "No") . "\n";

// Example 10: Array validation with unique
echo "\n=== Example 10: Array Unique Validation ===\n";
$validator10 = new Validator(
    ['emails' => ['test1@example.com', 'test2@example.com', 'test3@example.com']],
    ['emails.*' => ['required', 'email', 'unique:users,email']]
);
$result10 = $validator10->validate();
echo "Valid: " . ($result10 ? "Yes" : "No") . "\n";
if (!$result10) {
    print_r($validator10->errors());
}

// Example 11: Array validation with unique and ignore
echo "\n=== Example 11: Array Unique with Ignore ===\n";
$validator11 = new Validator(
    [
        'emails' => ['test1@example.com', 'test2@example.com'],
        'id' => 1
    ],
    ['emails.*' => ['required', 'email', 'unique:users,email,id:1']]
);
$result11 = $validator11->validate();
echo "Valid: " . ($result11 ? "Yes" : "No") . "\n";

// Example 12: Array validation with exists
echo "\n=== Example 12: Array Exists Validation ===\n";
$validator12 = new Validator(
    ['category_ids' => [1, 2, 3, 4, 5]],
    ['category_ids.*' => ['required', 'integer', 'exists:categories,id']]
);
$result12 = $validator12->validate();
echo "Valid: " . ($result12 ? "Yes" : "No") . "\n";
if (!$result12) {
    print_r($validator12->errors());
}

// Example 13: Array validation with exists and conditions
echo "\n=== Example 13: Array Exists with Conditions ===\n";
$validator13 = new Validator(
    ['category_ids' => [1, 2, 3]],
    ['category_ids.*' => ['required', 'integer', 'exists:categories,id,status:active']]
);
$result13 = $validator13->validate();
echo "Valid: " . ($result13 ? "Yes" : "No") . "\n";

// Example 14: Unique Rule Object with field reference
echo "\n=== Example 14: Unique with Field Reference ===\n";
$validator14 = new Validator(
    ['email' => 'test@example.com', 'user_id' => 5],
    ['email' => [new Unique('users', 'email', ['id' => 'user_id'])]]
);
$result14 = $validator14->validate();
echo "Valid: " . ($result14 ? "Yes" : "No") . "\n";
echo "Note: This uses 'user_id' field value (5) as ignore condition\n";

echo "\n=== All Examples Completed ===\n";

