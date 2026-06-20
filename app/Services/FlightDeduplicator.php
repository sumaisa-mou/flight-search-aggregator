<?php

namespace App\Services;

use App\Data\AlternativeOffer;
use App\Data\DedupedFlight;
use App\Data\NormalizedFlight;

class FlightDeduplicator
{
    /**
     * @param  NormalizedFlight[]  $flights
     * @return DedupedFlight[]
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
            $primary = $group[0];
            $alternatives = array_map(
                fn (NormalizedFlight $flight) => new AlternativeOffer(
                    source: $flight->source,
                    price: $flight->price,
                ),
                array_slice($group, 1),
            );

            $result[] = new DedupedFlight(primary: $primary, alternatives: $alternatives);
        }

        return $result;
    }
}
