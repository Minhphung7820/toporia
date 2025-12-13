<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use Toporia\Framework\Http\FormRequest;
use Toporia\Framework\Support\Str;

/**
 * Create Product Request
 *
 * Example FormRequest for creating a product.
 * Demonstrates:
 * - Authorization
 * - Validation rules
 * - Custom messages
 * - Prepare for validation
 * - Custom validator configuration
 *
 * @package App\Presentation\Http\Requests
 */
final class CreateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Example: Check if user is authenticated
        return $this->user() !== null;
    }

    /**
     * Get validation rules.
     *
     * @return array<string, string|array>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|integer|exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'image' => 'nullable|string|url',
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Product title is required',
            'title.max' => 'Product title cannot exceed 255 characters',
            'price.required' => 'Product price is required',
            'price.numeric' => 'Product price must be a number',
            'price.min' => 'Product price must be at least 0',
            'category_id.exists' => 'Selected category does not exist',
        ];
    }

    /**
     * Get custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'tags.*' => 'tag',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Example: Generate slug from title
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->request->has('title')) {
            $this->merge([
                'slug' => Str::slug($this->request->input('title')),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     *
     * Example: Add custom validation logic
     *
     * @param \Toporia\Framework\Validation\Contracts\ValidatorInterface $validator
     * @return void
     */
    public function withValidator(\Toporia\Framework\Validation\Contracts\ValidatorInterface $validator): void
    {
        // Example: Add custom validation after standard rules
        // $validator->after(function ($validator) {
        //     // Custom validation logic
        // });
    }
}
