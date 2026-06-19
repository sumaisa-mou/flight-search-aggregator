<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchFlightsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => 'required|string|size:3|alpha',
            'to' => 'required|string|size:3|alpha|different:from',
            'passengers' => 'required|integer|min:1|max:9',
            'date' => 'required|date|after_or_equal:today',
            'sort' => 'sometimes|in:price,duration,departure',
            'maxStops' => 'sometimes|integer|min:0|max:10',
            'carrier' => 'sometimes|string|size:2|alpha',
        ];
    }
}
