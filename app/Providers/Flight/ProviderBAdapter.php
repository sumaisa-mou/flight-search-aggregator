<?php

namespace App\Providers\Flight;

use App\Data\Money;
use App\Data\NormalizedFlight;
use App\Data\SearchCriteria;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

class ProviderBAdapter implements FlightProviderInterface
{
    public function name(): string
    {
        return 'b';
    }

    public function search(SearchCriteria $criteria): array
    {
        $flights = Http::timeout(config('flights.providers.b.timeout'))
            ->get(config('flights.providers.b.url'), [
                'origin' => $criteria->from,
                'destination' => $criteria->to,
                'date' => $criteria->date,
            ])
            ->throw()
            ->json('data', []);

        return array_map(fn (array $f) => NormalizedFlight::create(
            carrier: $f['airline_code'],
            flightNumber: $f['number'],
            origin: $f['origin'],
            destination: $f['destination'],
            departureAt: DateTimeImmutable::createFromFormat('Y-m-d H:i', $f['departure_time']),
            arrivalAt: DateTimeImmutable::createFromFormat('Y-m-d H:i', $f['arrival_time']),
            stops: $f['segments'],
            price: Money::fromMajorUnits($f['price']['amount'], $f['price']['currency']),
            source: $this->name(),
        ), $flights);
    }
}