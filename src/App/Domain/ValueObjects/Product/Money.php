<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects\Product;

use InvalidArgumentException;

/**
 * Money Value Object
 *
 * Represents monetary value with currency.
 * Immutable - all operations return new instances.
 *
 * Clean Architecture:
 * - Domain layer Value Object
 * - Pure business logic, no framework dependencies
 * - Encapsulates validation rules
 *
 * SOLID Principles:
 * - Single Responsibility: Money representation and arithmetic
 * - Immutability: All operations return new instances
 */
final class Money
{
    /**
     * @param float $amount Amount in currency units (must be >= 0)
     * @param string $currency ISO 4217 currency code (e.g., 'USD', 'VND')
     */
    public function __construct(
        public readonly float $amount,
        public readonly string $currency = 'VND'
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }

        if (empty($currency)) {
            throw new InvalidArgumentException('Currency code cannot be empty');
        }
    }

    /**
     * Create Money from amount.
     *
     * @param float $amount Amount
     * @param string $currency Currency code
     * @return self New Money instance
     */
    public static function fromAmount(float $amount, string $currency = 'VND'): self
    {
        return new self($amount, $currency);
    }

    /**
     * Create zero Money.
     *
     * @param string $currency Currency code
     * @return self Zero Money instance
     */
    public static function zero(string $currency = 'VND'): self
    {
        return new self(0.0, $currency);
    }

    /**
     * Add money (same currency only).
     *
     * @param Money $other Money to add
     * @return self New Money instance
     * @throws InvalidArgumentException If currencies differ
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtract money (same currency only).
     *
     * @param Money $other Money to subtract
     * @return self New Money instance
     * @throws InvalidArgumentException If currencies differ or result is negative
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    /**
     * Multiply by factor.
     *
     * @param float $factor Factor to multiply by
     * @return self New Money instance
     */
    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Factor cannot be negative');
        }
        return new self($this->amount * $factor, $this->currency);
    }

    /**
     * Check if amount is zero.
     *
     * @return bool True if zero
     */
    public function isZero(): bool
    {
        return abs($this->amount) < 0.01; // Floating point tolerance
    }

    /**
     * Check if greater than other Money.
     *
     * @param Money $other Money to compare
     * @return bool True if greater
     */
    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    /**
     * Check if less than other Money.
     *
     * @param Money $other Money to compare
     * @return bool True if less
     */
    public function isLessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    /**
     * Check if equal to other Money.
     *
     * @param Money $other Money to compare
     * @return bool True if equal
     */
    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency
            && abs($this->amount - $other->amount) < 0.01;
    }

    /**
     * Format money for display.
     *
     * @return string Formatted money string
     */
    public function format(): string
    {
        return match($this->currency) {
            'VND' => number_format($this->amount, 0, ',', '.') . ' ₫',
            'USD' => '$' . number_format($this->amount, 2, '.', ','),
            'EUR' => '€' . number_format($this->amount, 2, '.', ','),
            default => number_format($this->amount, 2, '.', ',') . ' ' . $this->currency
        };
    }

    /**
     * Assert same currency.
     *
     * @param Money $other Money to check
     * @throws InvalidArgumentException If currencies differ
     */
    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }

    /**
     * Convert to array.
     *
     * @return array{amount: float, currency: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
