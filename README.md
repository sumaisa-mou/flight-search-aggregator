# Flight Aggregator API

Small Laravel service for the iBox Lab senior backend take-home. One search query, three mock providers with completely different JSON shapes, one unified response. Plus a tiny booking endpoint that stores a flight snapshot and hands back a public reference.

Architecture notes and trade-offs are in `ARCHITECTURE.md`. This file is just enough to get it running.

## Stack

PHP 8.3, Laravel 12, MySQL for bookings, Redis for short-TTL search caching, Spatie `laravel-data` for the canonical DTOs, PHPUnit for the test suite.

## Getting it running

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Then set the database + cache config in `.env`:

```env
APP_TIMEZONE=UTC
DB_CONNECTION=mysql
DB_DATABASE=flight_aggregator
DB_USERNAME=root
DB_PASSWORD=
CACHE_STORE=redis
```

Create the database and run the booking migration:

```bash
mysql -u root -e "CREATE DATABASE flight_aggregator;"
php artisan migrate
php artisan serve
```

Redis just needs to be running locally — `brew services start redis` on macOS. If Redis is down, search still works, every request just hits the providers cold. Caching is optimization, not correctness.

The three mock providers are served from this same app under `/mock/provider-a`, `/mock/provider-b`, `/mock/provider-c`, so a single `php artisan serve` is enough to run everything.

## Running the tests

```bash
php artisan test
```

Tests use sqlite `:memory:` and the `array` cache driver, so you don't need MySQL or Redis just to run the suite.

## The endpoints

### Search

```
GET /api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2
```

Optional: `sort=price|duration|departure` (defaults to price), `maxStops=0`, `carrier=EK`.

```bash
curl "http://localhost:8000/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2"
```

You get back `data` (deduplicated, sorted flights) and `meta` reporting per-provider status plus a `complete` flag. `complete: false` means at least one provider failed or timed out, but the survivors still made it into `data`. Prices are integer minor units — `39900` is $399.00.

### Create a booking

```
POST /api/bookings
```

```bash
curl -X POST http://localhost:8000/api/bookings \
  -H "Content-Type: application/json" \
  -d '{
    "flight_id": "abc123",
    "carrier": "EK",
    "flight_number": "EK585",
    "origin": "DAC",
    "destination": "DXB",
    "departure_at": "2026-07-01T03:45:00+00:00",
    "arrival_at": "2026-07-01T06:50:00+00:00",
    "stops": 0,
    "price": { "amount": 39900, "currency": "USD" },
    "source": "b",
    "passengers": [
      { "name": "Mou Sumaisa", "passport": "BD1234567" }
    ]
  }'
```

Returns `201` with a `BKG-XXXXXX` reference. Validation lives in `StoreBookingRequest` — bad input gets `422` with field errors.

### Fetch a booking

```
GET /api/bookings/{reference}
```

```bash
curl http://localhost:8000/api/bookings/BKG-XXXXXX
```

`404` if the reference doesn't exist.

## Where things live

```
app/
  Http/Controllers/   FlightSearchController, BookingController, MockProviderController
  Http/Requests/      SearchFlightsRequest, StoreBookingRequest
  Http/Resources/     FlightSearchResource, FlightResource, BookingResource
  Services/           FlightSearchService, FlightAggregator, FlightDeduplicator
  Providers/Flight/   FlightProviderInterface + ProviderA/B/CAdapter
  Data/               NormalizedFlight, SearchCriteria, Money, AggregatedResult, SearchResult
  Models/             Booking
config/flights.php    provider URLs, per-provider timeouts, cache TTL
tests/Fixtures/       raw provider JSON used by adapter + feature tests
```

That's it. Read `ARCHITECTURE.md` for why any of this looks the way it does.