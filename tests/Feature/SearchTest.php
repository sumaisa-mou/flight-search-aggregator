<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_mock_provider_routes_return_their_payloads(): void
    {
        $this->getJson('/mock/provider-a')->assertOk()->assertJsonStructure(['flights']);
        $this->getJson('/mock/provider-b')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/mock/provider-c')->assertOk()->assertJsonStructure(['results']);
    }

    public function test_returns_unified_deduped_response(): void
    {
        $this->fakeProvidersFromMockRoutes();

        $response = $this->getJson($this->searchUrl());

        // raw: 4 (A) + 3 (B) + 3 (C) = 10. ek585 collides 3-way, bs220 and aa101 each collide 2-way.
        // 10 - 4 collisions = 6 unique flights.
        $response->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('meta.complete', true)
            ->assertJsonCount(3, 'meta.providers');
    }

    public function test_returns_sorted_data(): void
    {
        $this->fakeProvidersFromMockRoutes();

        // default sort is price ascending — bs118 at $265 is cheapest.
        $this->getJson($this->searchUrl())
            ->assertJsonPath('data.0.flightNumber', 'BS118');

        // sort by duration — ek585 is the shortest hop at 3h 5m.
        $this->getJson($this->searchUrl(['sort' => 'duration']))
            ->assertJsonPath('data.0.flightNumber', 'EK585');

        // sort by departure — ek585 leaves earliest at 03:45 utc.
        $this->getJson($this->searchUrl(['sort' => 'departure']))
            ->assertJsonPath('data.0.flightNumber', 'EK585');
    }

    public function test_returns_filtered_data(): void
    {
        $this->fakeProvidersFromMockRoutes();

        // maxStops=0 should leave only direct flights — aa101, aa205, ek585.
        $response = $this->getJson($this->searchUrl(['maxStops' => 0]));
        $response->assertJsonCount(3, 'data');
        foreach ($response->json('data') as $flight) {
            $this->assertSame(0, $flight['stops']);
        }

        // carrier=EK should leave only EK585.
        $this->getJson($this->searchUrl(['carrier' => 'EK']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.flightNumber', 'EK585');
    }

    private function searchUrl(array $extra = []): string
    {
        $params = array_merge([
            'from' => 'DAC',
            'to' => 'DXB',
            'date' => '2026-07-01',
            'passengers' => 2,
        ], $extra);

        return '/api/flights/search?'.http_build_query($params);
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