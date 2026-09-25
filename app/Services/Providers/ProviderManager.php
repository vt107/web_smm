<?php

namespace App\Services\Providers;

use App\Models\Provider;
use InvalidArgumentException;

class ProviderManager
{
    public function for(Provider $provider): ProviderClient
    {
        return match ($provider->driver) {
            'perfectpanel' => new PerfectPanelClient($provider),
            default => throw new InvalidArgumentException("Unsupported provider driver [{$provider->driver}]"),
        };
    }
}
