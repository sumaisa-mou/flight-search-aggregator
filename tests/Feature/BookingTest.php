<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_booking_and_returns_reference(): void
    {
        $response = $this->postJson('/api/bookings', $this->validPayload());

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'reference',
                    'flight' => ['id', 'carrier', 'flightNumber', 'origin', 'destination', 'departureAt', 'arrivalAt', 'stops', 'price' => ['amount', 'currency'], 'source'],
                    'passengers',
                    'createdAt',
                ],
            ])
            ->assertJsonPath('data.flight.carrier', 'EK')
            ->assertJsonPath('data.flight.flightNumber', 'EK585')
            ->assertJsonPath('data.flight.price.amount', 39900);

        $this->assertStringStartsWith('BKG-', $response->json('data.reference'));
        $this->assertSame(1, Booking::count());
    }

    public function test_round_trip_returns_same_booking_by_reference(): void
    {
        $created = $this->postJson('/api/bookings', $this->validPayload());
        $reference = $created->json('data.reference');

        $fetched = $this->getJson("/api/bookings/{$reference}");

        $fetched->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.flight.flightNumber', 'EK585')
            ->assertJsonPath('data.passengers.0.name', 'Mou Sumaisa');
    }

    public function test_unknown_reference_returns_404(): void
    {
        $this->getJson('/api/bookings/BKG-DOES-NOT-EXIST')->assertNotFound();
    }

    public function test_rejects_invalid_payload_with_422(): void
    {
        $this->postJson('/api/bookings', [])->assertStatus(422);
    }

    public function test_rejects_payload_with_arrival_before_departure(): void
    {
        $payload = $this->validPayload();
        $payload['arrival_at'] = '2026-07-01T02:00:00+00:00';
        $payload['departure_at'] = '2026-07-01T03:45:00+00:00';

        $this->postJson('/api/bookings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['arrival_at']);
    }

    public function test_lowercases_input_is_normalized_to_uppercase(): void
    {
        $payload = $this->validPayload();
        $payload['carrier'] = 'ek';
        $payload['origin'] = 'dac';

        $response = $this->postJson('/api/bookings', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.flight.carrier', 'EK')
            ->assertJsonPath('data.flight.origin', 'DAC');
    }

    private function validPayload(): array
    {
        return [
            'flight_id' => 'abc123',
            'carrier' => 'EK',
            'flight_number' => 'EK585',
            'origin' => 'DAC',
            'destination' => 'DXB',
            'departure_at' => '2026-07-01T03:45:00+00:00',
            'arrival_at' => '2026-07-01T06:50:00+00:00',
            'stops' => 0,
            'price' => ['amount' => 39900, 'currency' => 'USD'],
            'source' => 'b',
            'passengers' => [
                ['name' => 'Mou Sumaisa', 'passport' => 'BD1234567'],
            ],
        ];
    }
}