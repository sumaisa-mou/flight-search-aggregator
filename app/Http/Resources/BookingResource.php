<?php

namespace App\Http\Resources;

use App\Models\Booking;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Booking $resource
 */
class BookingResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $b = $this->resource;

        return [
            'data' => [
                'reference' => $b->reference,
                'flight' => [
                    'id' => $b->flight_id,
                    'carrier' => $b->carrier,
                    'flightNumber' => $b->flight_number,
                    'origin' => $b->origin,
                    'destination' => $b->destination,
                    'departureAt' => $b->departure_at->format(DateTimeImmutable::ATOM),
                    'arrivalAt' => $b->arrival_at->format(DateTimeImmutable::ATOM),
                    'stops' => $b->stops,
                    'price' => [
                        'amount' => $b->price_amount,
                        'currency' => $b->price_currency,
                    ],
                    'source' => $b->source,
                ],
                'passengers' => $b->passengers,
                'createdAt' => $b->created_at->format(DateTimeImmutable::ATOM),
            ],
        ];
    }
}
