<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'flight_id' => $data['flight_id'],
            'carrier' => strtoupper($data['carrier']),
            'flight_number' => strtoupper($data['flight_number']),
            'origin' => strtoupper($data['origin']),
            'destination' => strtoupper($data['destination']),
            'departure_at' => $data['departure_at'],
            'arrival_at' => $data['arrival_at'],
            'stops' => $data['stops'],
            'price_amount' => $data['price']['amount'],
            'price_currency' => strtoupper($data['price']['currency']),
            'source' => $data['source'],
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
