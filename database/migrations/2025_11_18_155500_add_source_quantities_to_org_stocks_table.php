<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Nov 2025 17:50:33 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('org_stocks', 'source_quantity_in_submitted_orders')) {
                $table->decimal('source_quantity_in_submitted_orders', 18, 3)->default(0);
            }

            if (!Schema::hasColumn('org_stocks', 'source_quantity_to_be_picked')) {
                $table->decimal('source_quantity_to_be_picked', 18, 3)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (Schema::hasColumn('org_stocks', 'source_quantity_in_submitted_orders')) {
                $table->dropColumn('source_quantity_in_submitted_orders');
            }

            if (Schema::hasColumn('org_stocks', 'source_quantity_to_be_picked')) {
                $table->dropColumn('source_quantity_to_be_picked');
            }
        });
    }
};
