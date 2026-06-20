<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_booking_and_returns_reference(): void
    {
        $flightId = $this->searchAndPickFlightId('EK585');

        $response = $this->postJson('/api/bookings', $this->validPayload($flightId));

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
            ->assertJsonPath('data.flight.id', $flightId)
            ->assertJsonPath('data.flight.price.amount', 39900);

        $this->assertStringStartsWith('BKG-', $response->json('data.reference'));
        $this->assertSame(1, Booking::count());
    }

    public function test_round_trip_returns_same_booking_by_reference(): void
    {
        $flightId = $this->searchAndPickFlightId('EK585');
        $created = $this->postJson('/api/bookings', $this->validPayload($flightId));
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

    public function test_returns_410_when_flight_snapshot_is_missing(): void
    {
        Cache::flush();

        $this->postJson('/api/bookings', $this->validPayload('missing-flight-id'))
            ->assertStatus(410);
    }

    public function test_client_cannot_override_server_flight_snapshot(): void
    {
        $flightId = $this->searchAndPickFlightId('EK585');
        $payload = $this->validPayload($flightId);
        $payload['carrier'] = 'ZZ';
        $payload['price'] = ['amount' => 1, 'currency' => 'EUR'];

        $response = $this->postJson('/api/bookings', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.flight.carrier', 'EK')
            ->assertJsonPath('data.flight.price.amount', 39900)
            ->assertJsonPath('data.flight.price.currency', 'USD');
    }

    private function validPayload(string $flightId): array
    {
        return [
            'flight_id' => $flightId,
            'passengers' => [
                ['name' => 'Mou Sumaisa', 'passport' => 'BD1234567'],
            ],
        ];
    }

    private function searchAndPickFlightId(string $flightNumber): string
    {
        Cache::flush();
        $this->fakeProvidersFromMockRoutes();

        $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2');

        $flight = collect($response->json('data'))
            ->firstWhere('flightNumber', $flightNumber);

        $this->assertNotNull($flight);

        return $flight['id'];
    }

    private function fakeProvidersFromMockRoutes(): void
    {
        Http::fake([
            '*provider-a*' => Http::response($this->getJson('/mock/provider-a')->json(), 200),
            '*provider-b*' => Http::response($this->getJson('/mock/provider-b')->json(), 200),
            '*provider-c*' => Http::response($this->getJson('/mock/provider-c')->json(), 200),
        ]);
    }
}
