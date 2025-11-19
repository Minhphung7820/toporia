<?php

declare(strict_types=1);

/**
 * English Translation File - Validation
 *
 * Validation error messages.
 */

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute must be a valid email address.',
    'min' => [
        'string' => 'The :attribute must be at least :min characters.',
        'numeric' => 'The :attribute must be at least :min.',
    ],
    'max' => [
        'string' => 'The :attribute may not be greater than :max characters.',
        'numeric' => 'The :attribute may not be greater than :max.',
    ],
    'unique' => 'The :attribute has already been taken.',
    'exists' => 'The selected :attribute is invalid.',
];

