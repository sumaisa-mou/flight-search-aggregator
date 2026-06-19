<?php

namespace App\Data;

final readonly class AggregatedResult
{
    /**
     * @param  NormalizedFlight[]  $flights
     * @param  array<int, array{name: string, status: string, count: int}>  $providerStatuses
     */
    public function __construct(
        public array $flights,
        public array $providerStatuses,
    ) {}

    public function isComplete(): bool
    {
        foreach ($this->providerStatuses as $status) {
            if ($status['status'] !== 'ok') {
                return false;
            }
        }

        return true;
    }
}