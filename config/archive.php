<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

return [

    /*
     * How far back dispatched emails and their tracking events are kept in the operational database.
     * Anything older is archived away, so it can no longer be recounted or reaggregated from source.
     * Starts cautious at 3 years; tighten via env once the archive is proven.
     */
    'email_retention_days' => (int) env('EMAIL_RETENTION_DAYS', 1095),

    /*
     * The email archiver pauses between delete batches while any replica is further behind than this,
     * so archiving can never build up the WAL backlog that once filled boro's disk.
     */
    'email_max_replication_lag_mb' => (int) env('EMAIL_ARCHIVE_MAX_REPLICATION_LAG_MB', 256),

];
