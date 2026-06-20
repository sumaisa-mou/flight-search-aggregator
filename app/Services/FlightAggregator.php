<?php

namespace App\Services;

use App\Data\AggregatedResult;
use App\Data\SearchCriteria;
use App\Providers\Flight\FlightProviderInterface;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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
        $providers = [];

        foreach ($this->providers as $provider) {
            $providers[$provider->name()] = $provider;
        }

        $responses = Http::pool(function (Pool $pool) use ($providers, $criteria) {
            foreach ($providers as $provider) {
                $provider->request($pool, $criteria);
            }
        });

        foreach ($providers as $name => $provider) {
            $response = $responses[$name] ?? null;

            try {
                if ($response instanceof Throwable) {
                    throw $response;
                }

                if (! $response instanceof Response) {
                    throw new \RuntimeException('Provider response missing from pool.');
                }

                if (! $response->successful()) {
                    throw new \RuntimeException("Provider returned HTTP {$response->status()}.");
                }

                $flights = $provider->normalize($response);
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
