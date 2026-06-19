<?php

namespace App\Providers\Flight;

use App\Data\NormalizedFlight;
use App\Data\SearchCriteria;

interface FlightProviderInterface
{
    public function name(): string;

    /** @return NormalizedFlight[] */
    public function search(SearchCriteria $criteria): array;
}