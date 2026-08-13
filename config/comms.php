<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

return [

    /*
     * How far back dispatched emails and their tracking events are kept in the operational database.
     * Anything older is archived away, so it can no longer be recounted or reaggregated from source.
     */
    'email_retention_days' => (int) env('EMAIL_RETENTION_DAYS', 90),

];
