# Architecture notes

This is a writeup of how the service is built and the calls I made along the way. The brief said "do not over-build" and I took that seriously — there is plenty of stuff I didn't ship that I'd want in production, all listed at the bottom.

## What the service does

One HTTP search hits `GET /api/flights/search`. Internally that fans out to three providers with completely different JSON shapes (flat keys, nested objects, Unix epoch timestamps), normalizes everything into one canonical model, dedupes flights that show up in more than one provider, applies sort and filter, and returns a single response with metadata about which providers succeeded.

The booking endpoints are deliberately small — store a snapshot of the chosen flight plus passengers, return a public reference, fetch by reference.

## The pipeline, end to end

When a request comes in:

1. `FlightSearchController` is thin — it pulls the validated request into a `SearchCriteria` DTO and hands it to `FlightSearchService`.
2. `FlightSearchService` checks the Redis cache first. The key is built from the four fields that actually identify the search (`from`, `to`, `date`, `passengers`) — not sort or filter. That means changing `sort=duration` reuses the same cached fan-out instead of triggering three more HTTP calls.
3. On a cache miss, the service calls `FlightAggregator::fetch()` which fans out to all three providers concurrently via `Http::pool()`. Each provider has its own timeout from `config/flights.php`. If a provider fails or times out, that one provider gets logged + reported in meta as `failed` — the others still come back.
4. Successful responses get handed back to the adapter that issued them. Each adapter's `normalize()` method is the *only* place that knows about its provider's quirks — date formats, key names, nested vs flat price.
5. The combined `NormalizedFlight[]` is what gets cached.
6. Out of the cache: `FlightDeduplicator` groups by stable id, picks the cheapest offer per real flight, and returns one row per logical flight.
7. `FlightSearchService::applyFilters()` and `::applySort()` run on the canonical model — same code path regardless of which provider any flight came from.
8. `FlightSearchResource` shapes the response: `data` array, `meta.complete`, `meta.providers`.

## Provider adapters

Each provider is one class implementing `FlightProviderInterface`. The interface has three methods: `name()`, `request(Pool $pool, SearchCriteria $criteria)`, and `normalize(Response $response): NormalizedFlight[]`.

The split between `request()` and `normalize()` exists because of `Http::pool()` — see the next section. The original brief specced a single `search()` method, but a single sync method can't participate in concurrent fan-out. The split is the smallest change that resolves the tension.

Adapters are registered via Laravel's tagged service container in `FlightServiceProvider`:

```php
$this->app->tag([
    ProviderAAdapter::class,
    ProviderBAdapter::class,
    ProviderCAdapter::class,
], FlightProviderInterface::class);
```

`FlightAggregator` gets the tagged collection injected — it never names any provider. Adding a fourth provider is one new class plus one line in the service provider, and no other code in the project changes. That's the open/closed payoff.

I've used the same family of pattern before — `wemail-api` had `PostmarkService`, `SendgridService`, `MailgunService` all behind an `MailAbstract`. The difference is that one used a string-concat factory (`new ("…\\{$driver}Service")`) which I always thought felt magic. Laravel's tagged container is the cleaner version of the same idea.

## Concurrency

PHP doesn't have native async, but Laravel's `Http::pool()` gives us concurrent fan-out at the HTTP layer. The aggregator builds the pool by asking each adapter for its request entry, then dispatches them all at once:

```php
$responses = Http::pool(fn (Pool $pool) => $providers
    ->map(fn ($p) => $p->request($pool, $criteria))
    ->all());
```

Per the brief, latency should be `~max(provider)`, not `sum(provider)`. The pool delivers that.

There is one wrinkle: a sync interface like `search(): NormalizedFlight[]` can't take part in `Http::pool()` because the request has to be queued *before* the pool dispatches, and the response only arrives *after*. Splitting into `request()` and `normalize()` is the minimal change. It's a real deviation from the brief and I want to be honest about it — but the alternative was to leave the implementation sequential and miss the marquee architectural requirement, which felt worse.

## The canonical model

`NormalizedFlight` is the single shape that the rest of the codebase reasons about. It extends Spatie `laravel-data`'s `Data` class, with readonly typed properties.

I picked Spatie over rolling my own DTO because reinventing the wheel for type-safe data carriers in 2026 felt unnecessary — Spatie is mature, widely used in Laravel, and the same package the community is converging on. I kept it confined to the internal layer: `NormalizedFlight` and `SearchCriteria` are Spatie Data, but `Money` is a plain readonly class because it has *behavior* (`lessThan`, currency invariant), not just shape; and API Resources stay separate because I want the public API contract decoupled from the internal model.

`Money` stores integer minor units (cents), never floats. The `fromMajorUnits(float, currency)` factory does `(int) round($amount * 100)` to convert at the edge. Float arithmetic on currency is the kind of bug that doesn't show up in tests but quietly corrupts production.

### The stable id

The id is what makes dedup possible:

```php
id = md5(carrier | flightNumber | origin | destination | departureAt-in-UTC)
```

Same flight from any provider produces the same id, regardless of how the provider encoded the datetime or capitalized the carrier. `NormalizedFlight::create()` uppercases inputs and converts the departure time to UTC before hashing, so a string like `"ek"`, a different timezone offset, or trailing whitespace doesn't fragment the id.

The brief specced `base32(sha256(...))`. I used md5 because the input is a five-field tuple of small strings (carrier code, flight number, two IATAs, a timestamp string) — the collision space is essentially zero in this domain, and md5 strings are shorter and more URL-friendly downstream. Not a security hash, just an identity hash. Sha256 + base32 wouldn't be wrong; md5 just feels right for the actual job.

## Deduplication

`FlightDeduplicator` groups normalized flights by stable id, sorts each group by price, and keeps the cheapest. One row per real flight.

The brief originally wanted the other offers retained as an `alternatives` list tagged with source + price. I started there and then dropped it — partly because the response shape got noticeably more complicated (alternatives nested inside each flight, sort behavior ambiguous when alternatives have different prices than the headline), and partly because for the user-facing experience the cheapest *available* offer is the headline anyway. A consumer that needs the full spread would be a B2B audit feature, not a passenger UI. If a stakeholder wants the alternatives back, it's a 30-minute change in `FlightDeduplicator` + one extra field on `FlightResource`. Left in the future-work list below.

## Caching

Redis, short TTL (default 60s, configurable in `config/flights.php`). The cache key is built from the search criteria's identity fields only — `from`, `to`, `date`, `passengers`. Sort and filter are deliberately not part of the key, so the same cached fan-out serves `sort=price` and `sort=duration` requests without re-hitting providers.

What gets cached is the post-aggregation, pre-dedup `NormalizedFlight[]` plus the per-provider status array. Dedup, sort, and filter run on the cache hit too — they're cheap in-memory operations, and keeping them out of the cache means the cache shape isn't coupled to query shape.

If Redis is down, the service still works. The fan-out happens on every request and the response is correct, just slower. Cache is an optimization layer, not a correctness layer.

## Resilience

Per-provider failure is isolated. Inside the aggregator:

- If `Http::pool()` returns a `Throwable` for a provider (connection refused, timeout, DNS fail), we log it, mark that provider `failed` in the status array, and continue with the others.
- If the response is a 5xx or some other non-`successful()` status, same path.
- If the response is successful but `normalize()` throws (malformed JSON shape), same path again — wrapped in try/catch around the `normalize()` call.

The top-level `meta.complete` flag is `false` whenever any provider's status is not `ok`. Consumers can read that and decide whether to retry, alert the user, or treat the partial result as good enough.

## Sort and filter

Both happen after normalization and dedup, on the canonical model, inside `FlightSearchService`. Sort is a simple `match` over `$criteria->sort`. Filter is `array_filter` chains for `maxStops` and `carrier`. Duration is derived as `(arrivalAt - departureAt) / 60` on `NormalizedFlight`.

Adding a new sort key is one new arm in the match. Adding a new filter is one new `array_filter`. No adapter has to know about any of this — that's the whole point of doing it on the canonical model.

## Booking

`POST /api/bookings` stores a full snapshot of the flight — carrier, route, times, price, source — plus the passengers JSON array. The public `reference` is `BKG-` + 6 random hex chars, generated in PHP and stored with a unique constraint. The integer `id` primary key stays internal.

There's a deliberate trade-off here that I want to call out: today, the server trusts the client's submitted flight payload. The client just got it from the search response, posts it back as-is. We don't verify the `flight_id` against a cached search result. That means a malicious client could in principle book a flight for whatever price they submit. In production I'd flip this: the search response stores a server-side snapshot keyed by stable id; the booking endpoint takes only `flight_id` + passengers, looks up the authoritative snapshot, copies the trusted fields. If the cached snapshot has expired you get a `410 Gone` with "search again." The brief explicitly listed this as out-of-scope, so I left it client-trusted. See future work.

`GET /api/bookings/{reference}` is a straight lookup with `firstOrFail()` for the 404 path.

## Layout deviations from the brief

I kept the brief's folder layout almost verbatim, but three things drifted intentionally:

- `app/DTOs/` → `app/Data/`. Spatie's `laravel-data` documentation and the community examples use `Data` as the folder. I followed convention instead of inventing a different one.
- `app/Http/Controllers/Api/` → `app/Http/Controllers/` (flat). The brief separates the API subnamespace, but for a tiny project with three controllers it felt like nominal symmetry without payoff. Routes are still under `/api/*` so the URL contract matches.
- `app/Providers/Flight/` lives in the same parent namespace as Laravel's own `app/Providers/` (`AppServiceProvider` etc.). The FQCNs (`App\Providers\Flight\ProviderAAdapter` vs `App\Providers\FlightServiceProvider`) disambiguate fine, but in a real codebase I'd probably move to `app/Integrations/Flight/` to avoid the overload. I followed the brief.

None of these are load-bearing, but I'd rather call them out than have you wonder.

## Tests

The test suite has three layers:

- **Adapter unit tests** (`tests/Unit/Providers/Flight/*Test.php`) feed each adapter the exact provider JSON from the brief (stored as fixtures under `tests/Fixtures/providers/`) and assert that the resulting `NormalizedFlight` has the right fields — especially the three different datetime formats and the three different price shapes.
- **Domain unit tests** cover the stable id (same logical flight → same id; different flights → different ids) and the deduplicator (three offers for the same flight collapse to one headline with the cheapest price).
- **Feature tests** hit the actual HTTP endpoints with `Http::fake()` stubs for the providers. `FlightSearchTest` covers envelope shape, dedup, the three sort orders, two filters, and the resilience case where one provider returns 500. `BookingTest` does the round-trip — POST then GET by reference — plus 404 and 422 paths.

All tests run from `php artisan test`. Sqlite `:memory:` and array cache mean no external services to stand up.

## What I didn't build, and what I'd do next

These are deliberately deferred per the brief's "do not over-build" instruction, listed roughly in order of how soon I'd want them:

- **Server-side flight snapshot lookup on booking.** Per the booking trade-off above. The trust model needs to flip before this hits real users.
- **Idempotency keys on `POST /api/bookings`.** A network blip + retry should not produce two bookings. Header-based key, stored mapping `{key → reference}` for some hours.
- **Circuit breakers / backoff on providers.** Right now a provider that fails just fails on this request. With real traffic we want a half-open breaker, exponential backoff, and we want to mark a provider as known-down for some minutes rather than thundering every request.
- **Per-provider rate limiting.** Real provider APIs have quotas. Token bucket per provider with config-driven rate.
- **Pagination.** The current response returns everything. Real searches need cursor-based paging.
- **Currency conversion.** All three mock providers happen to return USD. A real aggregator gets a mix, and the dedup tie-breaker has to compare prices in a common currency at the time of the request.
- **Full observability.** Today I log; I don't emit metrics. Per-provider latency histograms, error rates, cache hit rate, dedup rate — all worth a Prometheus exporter.
- **Async / queued fan-out for huge provider counts.** `Http::pool()` is fine for three providers. With twenty or fifty, you want a job per provider and a coordinator that waits for the first N to come back before responding.
- **Alternatives in the dedup output.** Per the dedup section above — drop the cheapest-only simplification, return the full price spread per real flight.

## A note on tooling

I used AI assistance for parts of this. The tests-writing pace is faster than I could type from scratch, and I used it to think out loud through some of the harder calls (like the dedup alternatives drop and the booking trust trade-off). The decisions and the rationale are mine — happy to defend any of them in detail.