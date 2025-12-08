<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Nov 2025 22:58:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaction_has_offer_allowances')) {
            Schema::table('transaction_has_offer_allowances', function (Blueprint $table) {
                try {
                    $table->index(['offer_campaign_id', 'order_id'], 'thoa_offer_campaign_order_idx');
                } catch (\Throwable $e) {
                    // Index might already exist or a database may not support this operation idempotently.
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transaction_has_offer_allowances')) {
            Schema::table('transaction_has_offer_allowances', function (Blueprint $table) {
                try {
                    $table->dropIndex('thoa_offer_campaign_order_idx');
                } catch (\Throwable $e) {
                    // Ignore if the index does not exist
                }
            });
        }
    }
};
