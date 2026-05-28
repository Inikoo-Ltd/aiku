<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 May 2026 23:01:00 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Http\Middleware\Sample;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes([
            'middleware' => [
                Sample::never(),
                'broadcast'
            ]
        ]);

        require base_path('routes/channels.php');
    }
}
