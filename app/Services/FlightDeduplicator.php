<?php

namespace App\Services;

use App\Data\NormalizedFlight;

class FlightDeduplicator
{
    /**
     * @param  NormalizedFlight[]  $flights
     * @return NormalizedFlight[]
     */
    public function dedupe(array $flights): array
    {
        $groups = [];
        foreach ($flights as $flight) {
            $groups[$flight->id][] = $flight;
        }

        $result = [];
        foreach ($groups as $group) {
            usort($group, fn (NormalizedFlight $a, NormalizedFlight $b) => $a->price->amount <=> $b->price->amount);
            $result[] = $group[0];
        }

        return $result;
    }
}
