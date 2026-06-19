<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'flight_id' => ['required', 'string'],
            'carrier' => ['required', 'string', 'size:2', 'alpha'],
            'flight_number' => ['required', 'string'],
            'origin' => ['required', 'string', 'size:3', 'alpha'],
            'destination' => ['required', 'string', 'size:3', 'alpha', 'different:origin'],
            'departure_at' => ['required', 'date'],
            'arrival_at' => ['required', 'date', 'after:departure_at'],
            'stops' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'array'],
            'price.amount' => ['required', 'integer', 'min:0'],
            'price.currency' => ['required', 'string', 'size:3', 'alpha'],
            'source' => ['required', 'string'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.name' => ['required', 'string', 'max:120'],
            'passengers.*.passport' => ['required', 'string', 'max:20'],
        ];
    }
}
