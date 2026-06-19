<?php

namespace App\Providers\Flight;

use App\Data\Money;
use App\Data\NormalizedFlight;
use App\Data\SearchCriteria;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

class ProviderAAdapter implements FlightProviderInterface
{
    public function name(): string
    {
        return 'a';
    }

    public function search(SearchCriteria $criteria): array
    {
        $flights = Http::timeout(config('flights.providers.a.timeout'))
            ->get(config('flights.providers.a.url'), [
                'from' => $criteria->from,
                'to' => $criteria->to,
                'date' => $criteria->date,
            ])
            ->throw()
            ->json('flights', []);

        return array_map(fn (array $f) => NormalizedFlight::create(
            carrier: $f['carrier'],
            flightNumber: $f['flight_no'],
            origin: $f['from'],
            destination: $f['to'],
            departureAt: new DateTimeImmutable($f['depart']),
            arrivalAt: new DateTimeImmutable($f['arrive']),
            stops: $f['stops'],
            price: Money::fromMajorUnits($f['fare_usd'], 'USD'),
            source: $this->name(),
        ), $flights);
    }
}