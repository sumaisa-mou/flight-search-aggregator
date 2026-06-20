<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'flight_id' => ['required', 'string'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.name' => ['required', 'string', 'max:120'],
            'passengers.*.passport' => ['required', 'string', 'max:20'],
        ];
    }
}
