<?php

namespace App\Services;

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

            $headline = array_shift($group);
            $alternatives = array_map(fn (NormalizedFlight $f) => [
                'source' => $f->source,
                'price' => $f->price,
            ], $group);

            $result[] = new DedupedFlight($headline, $alternatives);
        }

        return $result;
    }
}