<?php

namespace App\Providers\Flight;

use App\Data\NormalizedFlight;
use App\Data\SearchCriteria;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

interface FlightProviderInterface
{
    public function name(): string;

    public function request(Pool $pool, SearchCriteria $criteria): void;

    /** @return NormalizedFlight[] */
    public function normalize(Response $response): array;
}
