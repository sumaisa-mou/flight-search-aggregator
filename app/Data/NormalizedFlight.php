<?php

namespace App\Data;

use DateTimeImmutable;
use DateTimeZone;
use Spatie\LaravelData\Data;

class NormalizedFlight extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $carrier,
        public readonly string $flightNumber,
        public readonly string $origin,
        public readonly string $destination,
        public readonly DateTimeImmutable $departureAt,
        public readonly DateTimeImmutable $arrivalAt,
        public readonly int $stops,
        public readonly Money $price,
        public readonly string $source,
        public readonly array $alternatives = [],
    ) {}

    public static function create(
        string $carrier,
        string $flightNumber,
        string $origin,
        string $destination,
        DateTimeImmutable $departureAt,
        DateTimeImmutable $arrivalAt,
        int $stops,
        Money $price,
        string $source,
    ): self {
        $utc          = new DateTimeZone('UTC');
        $carrier      = strtoupper($carrier);
        $flightNumber = strtoupper($flightNumber);
        $origin       = strtoupper($origin);
        $destination  = strtoupper($destination);
        $departureAt  = $departureAt->setTimezone($utc);
        $arrivalAt    = $arrivalAt->setTimezone($utc);

        return new self(
            id: self::stableId($carrier, $flightNumber, $origin, $destination, $departureAt),
            carrier: $carrier,
            flightNumber: $flightNumber,
            origin: $origin,
            destination: $destination,
            departureAt: $departureAt,
            arrivalAt: $arrivalAt,
            stops: $stops,
            price: $price,
            source: $source,
        );
    }

    /**
     * @param  AlternativeOffer[]  $alternatives
     */
    public function withAlternatives(array $alternatives): self
    {
        return new self(
            id: $this->id,
            carrier: $this->carrier,
            flightNumber: $this->flightNumber,
            origin: $this->origin,
            destination: $this->destination,
            departureAt: $this->departureAt,
            arrivalAt: $this->arrivalAt,
            stops: $this->stops,
            price: $this->price,
            source: $this->source,
            alternatives: $alternatives,
        );
    }

    public function durationInMinutes(): int
    {
        return (int) (($this->arrivalAt->getTimestamp() - $this->departureAt->getTimestamp()) / 60);
    }

    private static function stableId(
        string $carrier,
        string $flightNumber,
        string $origin,
        string $destination,
        DateTimeImmutable $departureUtc,
    ): string {
        return md5(implode('|', [
            $carrier,
            $flightNumber,
            $origin,
            $destination,
            $departureUtc->format('c'),
        ]));
    }
}
