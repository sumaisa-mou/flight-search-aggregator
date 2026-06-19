<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SearchCriteria extends Data
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $date,
        public readonly int $passengers,
        public readonly string $sort = 'price',
        public readonly ?int $maxStops = null,
        public readonly ?string $carrier = null,
    ) {}

    public function cacheKey(): string
    {
        return sprintf(
            'flights:%s:%s:%s:%d',
            $this->from,
            $this->to,
            $this->date,
            $this->passengers,
        );
    }
}