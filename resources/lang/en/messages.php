<?php

declare(strict_types=1);

/**
 * English Translation File - Messages
 *
 * Example translation file showing various features:
 * - Simple translations
 * - Nested keys (dot notation)
 * - Placeholder replacements
 * - Pluralization
 */

return [
    // Simple translations
    'welcome' => 'Welcome',
    'hello' => 'Hello',
    'goodbye' => 'Goodbye',
    'thank_you' => 'Thank you',
    'please' => 'Please',
    'yes' => 'Yes',
    'no' => 'No',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'delete' => 'Delete',
    'edit' => 'Edit',
    'create' => 'Create',
    'update' => 'Update',
    'search' => 'Search',
    'submit' => 'Submit',
    'reset' => 'Reset',
    'back' => 'Back',
    'next' => 'Next',
    'previous' => 'Previous',
    'close' => 'Close',
    'open' => 'Open',
    'loading' => 'Loading...',
    'success' => 'Success',
    'error' => 'Error',
    'warning' => 'Warning',
    'info' => 'Information',

    // Messages with placeholders
    'welcome_user' => 'Welcome, :name!',
    'hello_user' => 'Hello :name, how are you?',
    'user_created' => 'User :name has been created successfully.',
    'user_updated' => 'User :name has been updated successfully.',
    'user_deleted' => 'User :name has been deleted successfully.',
    'item_count' => 'You have :count item(s).',
    'items_found' => 'Found :count item(s) matching your search.',

    // Nested keys (dot notation)
    'user' => [
        'name' => 'Name',
        'email' => 'Email',
        'password' => 'Password',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
        'profile' => [
            'title' => 'User Profile',
            'edit' => 'Edit Profile',
            'view' => 'View Profile',
        ],
    ],

    'product' => [
        'title' => 'Product',
        'name' => 'Product Name',
        'price' => 'Price',
        'description' => 'Description',
        'created' => 'Product :name has been created.',
        'updated' => 'Product :name has been updated.',
    ],

    // Pluralization examples
    'apples' => '{0} No apples|{1} One apple|[2,*] :count apples',
    'items' => '{0} No items|{1} One item|[2,*] :count items',
    'users' => '{0} No users|{1} One user|[2,*] :count users',
    'products' => '{0} No products|{1} One product|[2,*] :count products',
    'messages' => '{0} No messages|{1} One message|[2,*] :count messages',

    // Complex pluralization
    'user_count' => '{0} No users found|{1} One user found|[2,*] :count users found',
    'item_count_detailed' => '{0} You have no items|{1} You have one item|[2,*] You have :count items',
];

