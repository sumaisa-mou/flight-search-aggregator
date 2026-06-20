<?php

namespace App\Http\Resources;

use App\Data\AlternativeOffer;
use App\Data\DedupedFlight;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DedupedFlight $resource
 */
class FlightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $flight = $this->resource->primary;

        return [
            'id' => $flight->id,
            'carrier' => $flight->carrier,
            'flightNumber' => $flight->flightNumber,
            'origin' => $flight->origin,
            'destination' => $flight->destination,
            'departureAt' => $flight->departureAt->format(DateTimeImmutable::ATOM),
            'arrivalAt' => $flight->arrivalAt->format(DateTimeImmutable::ATOM),
            'stops' => $flight->stops,
            'durationMinutes' => $flight->durationInMinutes(),
            'price' => [
                'amount' => $flight->price->amount,
                'currency' => $flight->price->currency,
            ],
            'source' => $flight->source,
            'alternatives' => array_map(
                fn (AlternativeOffer $offer) => [
                    'source' => $offer->source,
                    'price' => [
                        'amount' => $offer->price->amount,
                        'currency' => $offer->price->currency,
                    ],
                ],
                $this->resource->alternatives,
            ),
        ];
    }
}