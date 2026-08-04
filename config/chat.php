<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

return [

    'presence' => [

        'heartbeat_seconds' => (int) env('CHAT_AGENT_HEARTBEAT_SECONDS', 120),
        'offline_after_seconds' => (int) env('CHAT_AGENT_OFFLINE_AFTER_SECONDS', 7200),
        'away_after_seconds' => (int) env('CHAT_AGENT_AWAY_AFTER_SECONDS', 3600),
        'abandon_after_seconds' => (int) env('CHAT_AGENT_ABANDON_AFTER_SECONDS', 14400),

    ],

];
