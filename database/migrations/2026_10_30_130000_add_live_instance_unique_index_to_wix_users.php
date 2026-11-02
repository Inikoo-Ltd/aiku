<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * A Wix site sends its install and order webhooks to one wix_user, so a second live row for
     * the same instance sends its orders to whichever row a query finds first (PRE-43). A closed
     * channel soft-deletes its wix_user, so a reinstall can still start a fresh row.
     *
     * Production was cleaned by hand on 30 Oct 2026. Copies of it taken earlier (staging, local)
     * still carry the duplicates: a customer-less spare never received an order and is dropped
     * everywhere, while a site owned twice keeps the row orders were routed to (the oldest) only
     * outside production. In production a remaining duplicate stops the migration instead.
     */
    public function up(): void
    {
        DB::statement('
            UPDATE wix_users spare SET deleted_at = now()
            WHERE spare.deleted_at IS NULL AND spare.customer_id IS NULL
            AND EXISTS (
                SELECT 1 FROM wix_users other
                WHERE other.wix_instance_id = spare.wix_instance_id AND other.deleted_at IS NULL AND other.id <> spare.id
            )
        ');

        if (!app()->isProduction()) {
            $extraChannelIds = DB::table('wix_users as extra')
                ->whereNull('extra.deleted_at')
                ->whereExists(fn ($query) => $query->from('wix_users as kept')
                    ->whereColumn('kept.wix_instance_id', 'extra.wix_instance_id')
                    ->whereNull('kept.deleted_at')
                    ->whereColumn('kept.id', '<', 'extra.id'))
                ->pluck('extra.customer_sales_channel_id', 'extra.id');

            DB::table('customer_sales_channels')->whereIn('id', $extraChannelIds->filter()->values())
                ->update(['status' => 'closed', 'state' => 'not_ready', 'closed_at' => now()]);
            DB::table('wix_users')->whereIn('id', $extraChannelIds->keys())->update(['deleted_at' => now()]);
        }

        DB::statement('
            CREATE UNIQUE INDEX wix_users_wix_instance_id_live_unique
            ON wix_users (wix_instance_id)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS wix_users_wix_instance_id_live_unique');
    }
};
