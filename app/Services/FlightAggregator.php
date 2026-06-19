<?php

namespace App\Services;

use App\Data\AggregatedResult;
use App\Data\SearchCriteria;
use App\Providers\Flight\FlightProviderInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class FlightAggregator
{
    /** @param  iterable<FlightProviderInterface>  $providers */
    public function __construct(
        private readonly iterable $providers,
    ) {}

    public function fetch(SearchCriteria $criteria): AggregatedResult
    {
        $allFlights = [];
        $statuses = [];

        foreach ($this->providers as $provider) {
            $name = $provider->name();

            try {
                $flights = $provider->search($criteria);
                $allFlights = [...$allFlights, ...$flights];
                $statuses[] = ['name' => $name, 'status' => 'ok', 'count' => count($flights)];
            } catch (Throwable $e) {
                Log::warning("flight provider {$name} failed", ['error' => $e->getMessage()]);
                $statuses[] = ['name' => $name, 'status' => 'failed', 'count' => 0];
            }
        }

        return new AggregatedResult($allFlights, $statuses);
    }
}