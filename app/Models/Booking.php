<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'reference',
        'flight_id',
        'carrier',
        'flight_number',
        'origin',
        'destination',
        'departure_at',
        'arrival_at',
        'stops',
        'price_amount',
        'price_currency',
        'source',
        'passengers',
    ];

    protected $casts = [
        'departure_at' => 'datetime',
        'arrival_at' => 'datetime',
        'passengers' => 'array',
    ];

    public static function generateReference(): string
    {
        return 'BKG-'.strtoupper(bin2hex(random_bytes(3)));
    }
}
