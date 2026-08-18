<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

return [

    /*
     * How far back dispatched emails and their tracking events are kept in the operational database.
     * Anything older is archived away, so it can no longer be recounted or reaggregated from source.
     *
     * 90 days is deliberate rather than arbitrary: SES stops reporting opens and clicks 60 days
     * after a send, so beyond that an email can never change again and archiving it loses nothing.
     * The remaining month is margin. Archived email stays readable — order pages fall back to the
     * archive automatically, and customer pages offer it from the table footer.
     */
    'email_retention_days' => (int) env('EMAIL_RETENTION_DAYS', 90),

    /*
     * The email archiver pauses between delete batches while any replica is further behind than this,
     * so archiving can never build up the WAL backlog that once filled boro's disk.
     */
    'email_max_replication_lag_mb' => (int) env('EMAIL_ARCHIVE_MAX_REPLICATION_LAG_MB', 256),

];
