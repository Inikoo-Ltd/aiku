<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Apr 2026 21:49:04 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

return [
    'enabled' => (bool) env('NEW_RELIC_ENABLED', false),
    'app_name' => env('NEW_RELIC_APP_NAME', sprintf('%s-%s', env('APP_NAME', 'laravel'), env('APP_ENV', 'production'))),
    'license_key' => env('NEW_RELIC_LICENSE_KEY'),
    'daemon_address' => env('NEW_RELIC_DAEMON_ADDRESS', ''),
    'labels' => env('NEW_RELIC_LABELS', ''),
];
