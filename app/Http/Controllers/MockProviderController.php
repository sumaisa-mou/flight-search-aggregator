<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class MockProviderController extends Controller
{
    public function a(): JsonResponse
    {
        return response()->json(['flights' => [
            ['carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB', 'depart' => '2026-07-01T08:00:00', 'arrive' => '2026-07-01T12:30:00', 'stops' => 0, 'fare_usd' => 320.00, 'flight_no' => 'AA101'],
            ['carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB', 'depart' => '2026-07-01T22:10:00', 'arrive' => '2026-07-02T02:40:00', 'stops' => 0, 'fare_usd' => 280.00, 'flight_no' => 'AA205'],
            ['carrier' => 'BS', 'from' => 'DAC', 'to' => 'DXB', 'depart' => '2026-07-01T09:15:00', 'arrive' => '2026-07-01T15:00:00', 'stops' => 1, 'fare_usd' => 310.00, 'flight_no' => 'BS220'],
            ['carrier' => 'EK', 'from' => 'DAC', 'to' => 'DXB', 'depart' => '2026-07-01T03:45:00', 'arrive' => '2026-07-01T06:50:00', 'stops' => 0, 'fare_usd' => 410.00, 'flight_no' => 'EK585'],
        ]]);
    }

    public function b(): JsonResponse
    {
        return response()->json(['data' => [
            ['airline_code' => 'BS', 'origin' => 'DAC', 'destination' => 'DXB', 'departure_time' => '2026-07-01 09:15', 'arrival_time' => '2026-07-01 15:00', 'segments' => 1, 'price' => ['amount' => 295, 'currency' => 'USD'], 'number' => 'BS220'],
            ['airline_code' => 'BS', 'origin' => 'DAC', 'destination' => 'DXB', 'departure_time' => '2026-07-01 14:30', 'arrival_time' => '2026-07-01 19:20', 'segments' => 1, 'price' => ['amount' => 265, 'currency' => 'USD'], 'number' => 'BS118'],
            ['airline_code' => 'EK', 'origin' => 'DAC', 'destination' => 'DXB', 'departure_time' => '2026-07-01 03:45', 'arrival_time' => '2026-07-01 06:50', 'segments' => 0, 'price' => ['amount' => 399, 'currency' => 'USD'], 'number' => 'EK585'],
        ]]);
    }

    public function c(): JsonResponse
    {
        return response()->json(['results' => [
            ['iata' => 'AA', 'route' => ['src' => 'DAC', 'dst' => 'DXB'], 'times' => ['dep' => 1782892800, 'arr' => 1782909000], 'layovers' => 0, 'total_price' => 335, 'currency' => 'USD', 'code' => 'AA101'],
            ['iata' => 'CJ', 'route' => ['src' => 'DAC', 'dst' => 'DXB'], 'times' => ['dep' => 1782885600, 'arr' => 1782903600], 'layovers' => 2, 'total_price' => 270, 'currency' => 'USD', 'code' => 'CJ300'],
            ['iata' => 'EK', 'route' => ['src' => 'DAC', 'dst' => 'DXB'], 'times' => ['dep' => 1782877500, 'arr' => 1782888600], 'layovers' => 0, 'total_price' => 405, 'currency' => 'USD', 'code' => 'EK585'],
        ]]);
    }
}