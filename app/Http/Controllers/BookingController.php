<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\FlightSnapshotStore;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, FlightSnapshotStore $snapshotStore): JsonResponse
    {
        $data = $request->validated();
        $flight = $snapshotStore->get($data['flight_id']);

        if ($flight === null) {
            throw new GoneHttpException('Flight snapshot expired or was not found. Search again.');
        }

        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'flight_id' => $flight->id,
            'carrier' => $flight->carrier,
            'flight_number' => $flight->flightNumber,
            'origin' => $flight->origin,
            'destination' => $flight->destination,
            'departure_at' => $flight->departureAt,
            'arrival_at' => $flight->arrivalAt,
            'stops' => $flight->stops,
            'price_amount' => $flight->price->amount,
            'price_currency' => $flight->price->currency,
            'source' => $flight->source,
            'passengers' => $data['passengers'],
        ]);

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    public function show(string $reference): BookingResource
    {
        $booking = Booking::where('reference', $reference)->firstOrFail();

        return new BookingResource($booking);
    }
}
