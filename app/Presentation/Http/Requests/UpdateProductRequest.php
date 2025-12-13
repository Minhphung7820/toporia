<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use Toporia\Framework\Http\FormRequest;

/**
 * Update Product Request
 *
 * Example FormRequest for updating a product.
 * Demonstrates:
 * - Different rules for update vs create
 * - Route parameter access
 * - Conditional validation
 *
 * @package App\Presentation\Http\Requests
 */
final class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Example: Check if user owns the product
        $productId = $this->route('id');
        // $product = Product::find($productId);
        // return $product && $product->user_id === $this->user()->id;

        return $this->user() !== null;
    }

    /**
     * Get validation rules.
     *
     * Note: All fields are optional for updates
     *
     * @return array<string, string|array>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'sometimes|required|numeric|min:0',
            'category_id' => 'sometimes|required|integer|exists:categories,id',
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
            'title.required' => 'Product title is required when updating',
            'price.required' => 'Product price is required when updating',
        ];
    }
}
