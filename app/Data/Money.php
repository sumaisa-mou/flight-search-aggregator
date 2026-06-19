<?php

namespace App\Data;

use DomainException;

final readonly class Money {
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}

    public static function fromMajorUnits(int|float $amount, string $currency): self {
        return new self((int) round($amount * 100), $currency);
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    public function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new DomainException("Cannot compare {$this->currency} with {$other->currency}");
        }

    }
}
