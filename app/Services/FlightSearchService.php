<?php

namespace App\Services;

use App\Data\DedupedFlight;
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
                $this->snapshotStore->putMany(array_map(fn (DedupedFlight $d) => $d->primary, $deduped));

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
     * @param  DedupedFlight[]  $flights
     * @return DedupedFlight[]
     */
    private function applyFilters(array $flights, SearchCriteria $criteria): array
    {
        if ($criteria->maxStops !== null) {
            $flights = array_filter($flights, fn (DedupedFlight $f) => $f->primary->stops <= $criteria->maxStops);
        }

        if ($criteria->carrier !== null) {
            $carrier = strtoupper($criteria->carrier);
            $flights = array_filter($flights, fn (DedupedFlight $f) => $f->primary->carrier === $carrier);
        }

        return array_values($flights);
    }

    /**
     * @param  DedupedFlight[]  $flights
     * @return DedupedFlight[]
     */
    private function applySort(array $flights, SearchCriteria $criteria): array
    {
        $comparator = match ($criteria->sort) {
            'duration' => fn (DedupedFlight $a, DedupedFlight $b) => $a->primary->durationInMinutes() <=> $b->primary->durationInMinutes(),
            'departure' => fn (DedupedFlight $a, DedupedFlight $b) => $a->primary->departureAt <=> $b->primary->departureAt,
            default => fn (DedupedFlight $a, DedupedFlight $b) => $a->primary->price->amount <=> $b->primary->price->amount,
        };

        usort($flights, $comparator);

        return $flights;
    }
}
