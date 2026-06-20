<?php

namespace App\Providers\Flight;

use App\Data\Money;
use App\Data\NormalizedFlight;
use App\Data\SearchCriteria;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

class ProviderAAdapter implements FlightProviderInterface
{
    public function name(): string
    {
        return 'a';
    }

    public function request(Pool $pool, SearchCriteria $criteria): void
    {
        $pool->as($this->name())
            ->timeout(config('flights.providers.a.timeout'))
            ->get(config('flights.providers.a.url'), [
                'from' => $criteria->from,
                'to' => $criteria->to,
                'date' => $criteria->date,
            ]);
    }

    public function normalize(Response $response): array
    {
        $flights = $response->json('flights', []);
        $timezone = new DateTimeZone((string) config('flights.provider_timezone', 'UTC'));

        return array_map(fn (array $f) => NormalizedFlight::create(
            carrier: $f['carrier'],
            flightNumber: $f['flight_no'],
            origin: $f['from'],
            destination: $f['to'],
            departureAt: new DateTimeImmutable($f['depart'], $timezone),
            arrivalAt: new DateTimeImmutable($f['arrive'], $timezone),
            stops: $f['stops'],
            price: Money::fromMajorUnits($f['fare_usd'], 'USD'),
            source: $this->name(),
        ), $flights);
    }
}
