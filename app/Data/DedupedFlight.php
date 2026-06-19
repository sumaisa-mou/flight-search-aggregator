<?php

namespace App\Data;

final readonly class DedupedFlight
{
    /**
     * @param  array<int, array{source: string, price: Money}>  $alternatives
     */
    public function __construct(
        public NormalizedFlight $headline,
        public array $alternatives,
    ) {}
}
