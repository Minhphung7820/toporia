<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

use Toporia\Framework\Validation\Validator;
use Toporia\Framework\Validation\Contracts\ValidatorInterface;
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Validates Requests Trait
 *
 * Provides validation methods for controllers.
 * Allows manual validation when FormRequest is not used.
 *
 * Performance: O(N*R) where N = fields, R = rules
 *
 * Clean Architecture:
 * - Single Responsibility: Only provides validation helpers
 * - Open/Closed: Extensible via custom rules
 *
 * SOLID Principles:
 * - S: Single responsibility - validation helpers only
 * - O: Open/Closed - works with any validation rules
 *
 * Usage:
 * ```php
 * class ProductController extends BaseController
 * {
 *     use ValidatesRequests;
 *
 *     public function store(Request $request)
 *     {
 *         $validated = $this->validate($request, [
 *             'title' => 'required|string|max:255',
 *             'price' => 'required|numeric|min:0',
 *         ]);
 *
 *         // Use validated data
 *     }
 * }
 * ```
 *
 * @package Toporia\Framework\Http
 */
trait ValidatesRequests
{
    /**
     * Validate the given request with the given rules.
     *
     * Performance: O(N*R) where N = fields, R = rules
     *
     * @param Request $request
     * @param array $rules
     * @param array $messages
     * @return array Validated data
     * @throws ValidationException
     */
    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        $validator = $this->getValidator();

        $passes = $validator->validate($request->all(), $rules, $messages);

        if (!$passes) {
            throw new ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Validate the given request with the given rules (returns on failure).
     *
     * Performance: O(N*R) where N = fields, R = rules
     *
     * @param Request $request
     * @param array $rules
     * @param array $messages
     * @return array Validated data
     */
    protected function validateOrFail(Request $request, array $rules, array $messages = []): array
    {
        return $this->validate($request, $rules, $messages);
    }

    /**
     * Get validator instance.
     *
     * Performance: O(1) - Creates or reuses validator
     *
     * @return ValidatorInterface
     */
    protected function getValidator(): ValidatorInterface
    {
        // Try to get from container first
        if (property_exists($this, 'container') && $this->container instanceof ContainerInterface) {
            if ($this->container->has(ValidatorInterface::class)) {
                return $this->container->get(ValidatorInterface::class);
            }
        }

        // Fallback to new instance
        return new Validator();
    }
}
