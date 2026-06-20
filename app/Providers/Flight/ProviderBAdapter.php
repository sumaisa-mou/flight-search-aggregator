<?php

namespace App\Providers\Flight;

use App\Data\Money;
use App\Data\NormalizedFlight;
use App\Data\SearchCriteria;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use RuntimeException;

class ProviderBAdapter implements FlightProviderInterface
{
    public function name(): string
    {
        return 'b';
    }

    public function request(Pool $pool, SearchCriteria $criteria): void
    {
        $pool->as($this->name())
            ->timeout(config('flights.providers.b.timeout'))
            ->get(config('flights.providers.b.url'), [
                'origin' => $criteria->from,
                'destination' => $criteria->to,
                'date' => $criteria->date,
            ]);
    }

    public function normalize(Response $response): array
    {
        $flights = $response->json('data', []);
        $timezone = new DateTimeZone((string) config('flights.provider_timezone', 'UTC'));

        return array_map(function (array $f) use ($timezone) {
            $departureAt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $f['departure_time'], $timezone);
            $arrivalAt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $f['arrival_time'], $timezone);

            if ($departureAt === false || $arrivalAt === false) {
                throw new RuntimeException('Provider B returned an invalid datetime.');
            }

            return NormalizedFlight::create(
                carrier: $f['airline_code'],
                flightNumber: $f['number'],
                origin: $f['origin'],
                destination: $f['destination'],
                departureAt: $departureAt,
                arrivalAt: $arrivalAt,
                stops: $f['segments'],
                price: Money::fromMajorUnits($f['price']['amount'], $f['price']['currency']),
                source: $this->name(),
            );
        }, $flights);
    }
}
