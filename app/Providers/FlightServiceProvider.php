<?php

namespace App\Providers;

use App\Providers\Flight\FlightProviderInterface;
use App\Providers\Flight\ProviderAAdapter;
use App\Providers\Flight\ProviderBAdapter;
use App\Providers\Flight\ProviderCAdapter;
use App\Services\FlightAggregator;
use Illuminate\Support\ServiceProvider;

class FlightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            ProviderAAdapter::class,
            ProviderBAdapter::class,
            ProviderCAdapter::class,
        ], FlightProviderInterface::class);

        $this->app->bind(FlightAggregator::class, fn ($app) => new FlightAggregator(
            $app->tagged(FlightProviderInterface::class),
        ));
    }
}