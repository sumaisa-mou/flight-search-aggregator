<?php

namespace App\Http\Resources;

use App\Data\SearchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SearchResult $resource
 */
class FlightSearchResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'data' => FlightResource::collection($this->resource->flights),
            'meta' => [
                'complete' => $this->resource->isComplete(),
                'providers' => $this->resource->providerStatuses,
            ],
        ];
    }
}
