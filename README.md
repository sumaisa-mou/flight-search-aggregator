# Flight Aggregator API

A small Laravel service that aggregates flight search results from multiple providers and exposes a booking API on top.

The API does two things:

- searches multiple flight providers and returns one unified result set
- creates and retrieves bookings using a stable flight identifier

The focus is on API design, separation of concerns, and a backend that is easy to extend.

For the reasoning behind the design, trade-offs, and future work, see `ARCHITECTURE.md`.

## Stack

- PHP 8.3
- Laravel 12
- MySQL for bookings
- Redis for short-lived search and booking snapshot cache
- Spatie `laravel-data` for internal data objects
- PHPUnit for tests

## Run locally

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set the basic environment values:

```env
APP_TIMEZONE=UTC
DB_CONNECTION=mysql
DB_DATABASE=flight_aggregator
DB_USERNAME=root
DB_PASSWORD=
CACHE_STORE=redis
```

Create the database, migrate, and start the app:

```bash
mysql -u root -e "CREATE DATABASE flight_aggregator;"
php artisan migrate
php artisan serve
```

Redis only powers caching. If Redis is down, search still works, but every request hits the mock providers directly.

The mock providers are served by the same app under:

- `/mock/provider-a`
- `/mock/provider-b`
- `/mock/provider-c`

So one `php artisan serve` process is enough to run everything locally.

## Run tests

```bash
php artisan test
```

The tests use in-memory sqlite and the array cache driver, so no MySQL or Redis setup is required.

## API

### Search flights

```http
GET /api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2
```

Optional query params:

- `sort=price|duration|departure` default is `price`
- `maxStops=0`
- `carrier=EK`

Example:

```bash
curl "http://localhost:8000/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2"
```

What the response includes:

- one unified `data` array
- a stable `id` for each logical flight
- one primary offer per flight
- `alternatives` when the same flight appears from other providers at different prices
- `meta.providers` so the client can see which providers succeeded or failed
- `meta.complete` so the client knows whether the result set is complete

Prices are returned in integer minor units. For example, `39900` means `$399.00`.

### Create a booking

```http
POST /api/bookings
```

Example:

```bash
curl -X POST http://localhost:8000/api/bookings \
  -H "Content-Type: application/json" \
  -d '{
    "flight_id": "2c19399bc16df54f7d4b0f3386f7f2fe",
    "passengers": [
      { "name": "Mou Sumaisa", "passport": "BD1234567" }
    ]
  }'
```

Important detail:

- the server does not trust the client to send price, carrier, or timing details
- the client books using `flight_id`
- the server looks up the cached flight snapshot from the earlier search and stores that authoritative version

If the snapshot is missing or expired, the API returns `410 Gone` and the client needs to search again.

### Fetch a booking

```http
GET /api/bookings/{reference}
```

Example:

```bash
curl http://localhost:8000/api/bookings/BKG-ABC123
```

Returns `404` when the booking reference does not exist.

## Project layout

```text
app/
  Data/               Canonical data objects
  Http/Controllers/   Search, booking, and mock provider controllers
  Http/Requests/      Input validation
  Http/Resources/     Public API response shaping
  Models/             Booking model
  Providers/Flight/   Provider adapters
  Services/           Aggregation, dedup, search, booking snapshot logic

config/flights.php    Provider URLs, timeouts, cache TTLs, provider timezone
routes/api.php        Public API routes
routes/web.php        Mock provider routes
tests/Feature/        End-to-end API tests
```

## Current behavior in plain terms

- Search calls the providers concurrently with `Http::pool()`
- Provider failures are isolated, so one bad provider does not break the whole response
- Duplicate flights are collapsed into one logical result
- The cheapest offer becomes the primary result
- Other offers for the same flight are returned under `alternatives`
- Booking is tied to the stable flight identifier, not to client-submitted flight details
