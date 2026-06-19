<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchFlightsRequest;
use Illuminate\Http\JsonResponse;

class FlightSearchController extends Controller
{
    public function search(SearchFlightsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => [],
            'meta' => [
                'complete' => true,
                'providers' => [],
            ],
        ]);
    }
}
