# Architecture Notes

How the search and booking flows are structured, why the boundaries fall where they do, and what would change to take the service further.

## What the service does

There are two main user flows:

1. A client searches for flights.
2. A client books one of the returned flights using its stable identifier.

The interesting part is the search flow. Three providers return similar flight information in three different JSON shapes. The service has to fan out to all of them, normalize the responses, collapse duplicates, keep the best offer visible, and still tell the client whether the result is complete.

## The shape of the system

The code is split into a few clear layers:

- controllers accept HTTP requests and return resources
- request classes validate input
- services coordinate search, aggregation, deduplication, and booking snapshot lookup
- provider adapters know how to talk to one provider each
- data objects hold the internal canonical model
- resources define the public API contract

That separation keeps provider-specific details away from the rest of the application.

## Search flow

The search path is:

1. `FlightSearchController` receives the request.
2. `SearchFlightsRequest` validates the query string.
3. The validated input is mapped into `SearchCriteria`.
4. `FlightSearchService` checks the cache using the identity of the search: `from`, `to`, `date`, and `passengers`.
5. On a cache miss, `FlightAggregator` queries all providers concurrently with `Http::pool()`.
6. Each provider adapter normalizes its own response into the same `NormalizedFlight` model.
7. `FlightDeduplicator` groups equivalent flights by stable ID.
8. The cheapest offer becomes the primary result, and the remaining offers are exposed under `alternatives`.
9. Filters and sorting are applied on the canonical model, not on raw provider payloads.
10. The final flights are also stored as short-lived booking snapshots keyed by `flight_id`.

The public response includes:

- `data`
- `meta.providers`
- `meta.complete`

That gives the client both the actual results and enough context to judge whether the response is partial.

## Why the provider layer looks the way it does

Each adapter implements the same interface:

- `name()`
- `request(Pool $pool, SearchCriteria $criteria)`
- `normalize(Response $response)`

This split is deliberate.

If the interface were just `search(): array`, the aggregator would have to call each provider synchronously. By splitting request creation from response normalization, the aggregator can queue every outbound call first, let Laravel dispatch them concurrently, and then hand each raw response back to the correct adapter.

That keeps orchestration in one place and provider quirks in another.

## Concurrency

Search latency should feel like `max(provider latency)` rather than `sum(provider latency)`.

That is why the aggregator uses `Http::pool()`. All provider requests are dispatched together. If one provider is slow, it does not force the others to wait in line behind it.

This is not async PHP in a broad sense. It is concurrent outbound HTTP fan-out at the integration boundary, which is what the search flow actually needs.

## Canonical model

`NormalizedFlight` is the shared internal shape used after provider responses are parsed.

It contains:

- stable `id`
- carrier and flight number
- route
- departure and arrival times
- stops
- primary price
- source provider
- alternative offers

The rest of the code does not need to know whether a provider used nested JSON, flat keys, or Unix timestamps.

`Money` stores integer minor units rather than floats. That avoids subtle rounding issues and makes sorting and comparison safe.

## Stable flight identifier

The stable ID is built from:

- carrier
- flight number
- origin
- destination
- departure timestamp in UTC

This matters because the same logical flight can appear from multiple providers. The stable ID is what lets the system recognize that:

- provider A's `EK585`
- provider B's `EK585`
- provider C's `EK585`

are all the same flight even though they came from different payload formats.

I normalize the timestamps to UTC before hashing so the ID is stable regardless of the app timezone or the provider's datetime representation.

## Deduplication

Deduplication means collapsing multiple provider records for the same real flight into one logical search result.

For example, if three providers all return `EK585` on the same route at the same departure time, showing all three as separate flights creates noise for the user. They are not three different flights. They are three offers for the same flight.

So the dedup step:

1. groups records by stable flight ID
2. sorts each group by price
3. keeps the cheapest offer as the main result
4. returns the rest under `alternatives`

That gives the user a clean list without hiding useful price differences.

### Why dedup has its own output type

`FlightDeduplicator` returns `DedupedFlight[]`, not `NormalizedFlight[]`. Each `DedupedFlight` is a `{ primary: NormalizedFlight, alternatives: AlternativeOffer[] }` pair.

`alternatives` is a post-dedup concept — it does not exist at the provider layer. Putting it on `NormalizedFlight` would mean a field that starts empty and gets filled later, breaking immutability and forcing a `withAlternatives()` clone method. A separate type avoids both:

```
NormalizedFlight[]  →  FlightDeduplicator::dedupe  →  DedupedFlight[]
```

`FlightSnapshotStore` still works with `NormalizedFlight` — the snapshot is the primary only — so the search service extracts primaries before handing them in.

## Caching

There are two cache uses here.

### Search cache

The aggregated provider result is cached for a short TTL. The cache key includes only the fields that define the search itself:

- `from`
- `to`
- `date`
- `passengers`

Sort and filter are intentionally left out of the key, because they can be applied cheaply after the canonical result is loaded.

### Booking snapshot cache

Each returned flight is also cached by `flight_id` for booking.

That allows the booking API to trust the server-side snapshot instead of trusting the client to send price and timing fields back unchanged.

If the snapshot is gone, booking returns `410 Gone` and the client must search again.

## Resilience

Provider failures are isolated.

If one provider:

- times out
- returns a non-2xx response
- returns a malformed payload

the service logs the failure, marks that provider as failed in `meta.providers`, and still returns any successful results from the other providers.

`meta.complete` becomes `false` whenever at least one provider fails.

That feels better for a search experience than turning the whole request into an error because one upstream dependency had a bad moment.

## Booking flow

The booking flow is server-authoritative.

The client sends:

- `flight_id`
- passenger details

The server:

1. looks up the cached flight snapshot
2. copies the trusted flight data into the booking record
3. stores the passengers
4. returns a public booking reference

This is much safer than accepting price, route, and source fields directly from the client.

## Why the API is shaped this way

The public search response is designed around what a frontend actually needs:

- a stable ID to refer to a chosen flight later
- one clear row per logical flight
- a visible cheapest price
- alternative provider prices when they exist
- provider completeness metadata

That keeps the response readable for people and predictable for downstream code.

## Testing

The project currently leans on feature tests rather than a large unit test matrix.

The feature tests cover:

- mock provider routes
- unified search response
- sorting
- filtering
- partial provider failure
- timezone-safe deduplication
- alternative offers in deduped results
- booking create and fetch flow
- expired snapshot handling
- protection against client-side flight data tampering

That gives decent confidence in the core flows without bloating the test suite.

## Trade-offs and what I would do next

The next things I would add, in rough order, are:

- idempotency keys for `POST /api/bookings`
- provider-level retry and circuit-breaker policies
- pagination for large search result sets
- metrics around provider latency, failure rate, and cache hit rate
- stronger persistence for search snapshots if bookings must survive cache loss
- support for mixed currencies and conversion
- richer alternative-offer metadata if the UI needs more than source and price
