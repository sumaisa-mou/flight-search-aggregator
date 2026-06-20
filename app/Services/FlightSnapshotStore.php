<?php

namespace App\Services;

use App\Data\NormalizedFlight;
use Illuminate\Support\Facades\Cache;

class FlightSnapshotStore
{
    public function putMany(array $flights): void
    {
        $ttl = (int) config('flights.booking_snapshot_ttl', config('flights.cache.ttl'));

        foreach ($flights as $flight) {
            Cache::put($this->key($flight->id), $flight, $ttl);
        }
    }

    public function get(string $flightId): ?NormalizedFlight
    {
        $flight = Cache::get($this->key($flightId));

        return $flight instanceof NormalizedFlight ? $flight : null;
    }

    private function key(string $flightId): string
    {
        return "flight_snapshot:{$flightId}";
    }
}
