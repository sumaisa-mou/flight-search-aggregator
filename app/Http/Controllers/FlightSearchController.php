<?php

namespace App\Http\Controllers;

use App\Data\SearchCriteria;
use App\Http\Requests\SearchFlightsRequest;
use App\Http\Resources\FlightSearchResource;
use App\Services\FlightSearchService;

class FlightSearchController extends Controller
{
    public function search(SearchFlightsRequest $request, FlightSearchService $service): FlightSearchResource
    {
        $criteria = SearchCriteria::from($request->validated());
        $result = $service->search($criteria);

        return new FlightSearchResource($result);
    }
}
