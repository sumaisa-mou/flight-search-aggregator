<?php

namespace App\Providers\Flight;

use App\Data\Money;
use App\Data\NormalizedFlight;
use App\Data\SearchCriteria;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

class ProviderCAdapter implements FlightProviderInterface
{
    public function name(): string
    {
        return 'c';
    }

    public function search(SearchCriteria $criteria): array
    {
        $flights = Http::timeout(config('flights.providers.c.timeout'))
            ->get(config('flights.providers.c.url'), [
                'src' => $criteria->from,
                'dst' => $criteria->to,
                'date' => $criteria->date,
            ])
            ->throw()
            ->json('results', []);

        return array_map(fn (array $f) => NormalizedFlight::create(
            carrier: $f['iata'],
            flightNumber: $f['code'],
            origin: $f['route']['src'],
            destination: $f['route']['dst'],
            departureAt: new DateTimeImmutable('@'.$f['times']['dep']),
            arrivalAt: new DateTimeImmutable('@'.$f['times']['arr']),
            stops: $f['layovers'],
            price: Money::fromMajorUnits($f['total_price'], $f['currency']),
            source: $this->name(),
        ), $flights);
    }
}