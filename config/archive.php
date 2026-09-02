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
     * How old a transactional record (order, delivery note, invoice, payment) must be before its
     * whole audit trail is archived. The record's own age decides, not the age of each audit row:
     * old records still collect machine audits from backfills, history imports and hydrators, and
     * the History tab falls back to the archive per record, so a split trail would hide its older
     * half. Kept separate from the email retention, which is pinned to the SES reporting window.
     */
    'audit_retention_days' => (int) env('AUDIT_RETENTION_DAYS', 120),

    /*
     * How far back per SKU and per location stock history is kept at daily granularity in the
     * operational database. Beyond it only the last snapshot of each month stays local; every
     * other day is moved to the archive database, where the UI reads it back transparently.
     *
     * Three years rather than two is an engineering choice: BackfillStockValuations and the cost
     * repairs rewrite everything from organisations.wac_calculations_start_date, and they only
     * ever touch the operational database, so a window that ends inside a range those sweeps
     * still rewrite would leave archived rows disagreeing with recomputed ones. The org and group
     * level daily series (organisation_stock_histories, group_stock_histories) is never archived,
     * so every dashboard and valuation total keeps its full history whatever this is set to.
     */
    'stock_history_retention_months' => (int) env('STOCK_HISTORY_RETENTION_MONTHS', 36),

    /*
     * The nightly stock history downsample. The historic backlog, 5,804 days back to March 2007,
     * was archived by hand on 31 Aug 2026; what is left is one new day a night, a two minute tick.
     * Bounded to five days a run so a missed night catches up and a bad one cannot touch more.
     * Still gated on the environment variable, so an environment that has not had its backlog
     * archived does not start eating it unattended.
     */
    'stock_history_nightly' => (bool) env('STOCK_HISTORY_NIGHTLY_ARCHIVE', false),

    /*
     * Every archiver pauses between delete batches while any replica is further behind than this,
     * so archiving can never build up the WAL backlog that once filled boro's disk.
     */
    'max_replication_lag_mb' => (int) env('EMAIL_ARCHIVE_MAX_REPLICATION_LAG_MB', 256),

];
