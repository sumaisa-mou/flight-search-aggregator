<?php

namespace App\Services;

use App\Data\NormalizedFlight;
use App\Data\SearchCriteria;
use App\Data\SearchResult;
use Illuminate\Support\Facades\Cache;

class FlightSearchService
{
    public function __construct(
        private readonly FlightAggregator $aggregator,
        private readonly FlightDeduplicator $deduplicator,
        private readonly FlightSnapshotStore $snapshotStore,
    ) {}

    public function search(SearchCriteria $criteria): SearchResult
    {
        $cached = Cache::remember(
            $criteria->cacheKey(),
            (int) config('flights.cache.ttl'),
            function () use ($criteria) {
                $result = $this->aggregator->fetch($criteria);
                $deduped = $this->deduplicator->dedupe($result->flights);
                $this->snapshotStore->putMany($deduped);

                return [
                    'flights' => $deduped,
                    'statuses' => $result->providerStatuses,
                ];
            },
        );

        $flights = $this->applyFilters($cached['flights'], $criteria);
        $flights = $this->applySort($flights, $criteria);

        return new SearchResult($flights, $cached['statuses']);
    }

    /**
     * @param  NormalizedFlight[]  $flights
     * @return NormalizedFlight[]
     */
    private function applyFilters(array $flights, SearchCriteria $criteria): array
    {
        if ($criteria->maxStops !== null) {
            $flights = array_filter($flights, fn (NormalizedFlight $f) => $f->stops <= $criteria->maxStops);
        }

        if ($criteria->carrier !== null) {
            $carrier = strtoupper($criteria->carrier);
            $flights = array_filter($flights, fn (NormalizedFlight $f) => $f->carrier === $carrier);
        }

        return array_values($flights);
    }

    /**
     * @param  NormalizedFlight[]  $flights
     * @return NormalizedFlight[]
     */
    private function applySort(array $flights, SearchCriteria $criteria): array
    {
        $comparator = match ($criteria->sort) {
            'duration' => fn (NormalizedFlight $a, NormalizedFlight $b) => $a->durationInMinutes() <=> $b->durationInMinutes(),
            'departure' => fn (NormalizedFlight $a, NormalizedFlight $b) => $a->departureAt <=> $b->departureAt,
            default => fn (NormalizedFlight $a, NormalizedFlight $b) => $a->price->amount <=> $b->price->amount,
        };

        usort($flights, $comparator);

        return $flights;
    }
}
