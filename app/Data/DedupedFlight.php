<?php

namespace App\Data;

final readonly class DedupedFlight
{
    /**
     * @param  AlternativeOffer[]  $alternatives
     */
    public function __construct(
        public NormalizedFlight $primary,
        public array $alternatives,
    ) {}
}