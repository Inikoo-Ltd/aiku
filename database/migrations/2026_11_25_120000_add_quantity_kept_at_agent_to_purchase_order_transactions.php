<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->decimal('quantity_kept_at_agent', 16, 3)->default(0)->comment('INI-525 units the buyer agreed stay at the agent: they count as accounted for, so the order settles without them, and can still ship later. History in data.kept_at_agent');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->dropColumn('quantity_kept_at_agent');
        });
    }
};
