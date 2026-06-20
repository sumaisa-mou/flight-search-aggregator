<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class AlternativeOffer extends Data
{
    public function __construct(
        public readonly string $source,
        public readonly Money $price,
    ) {}
}
