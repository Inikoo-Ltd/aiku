<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 12:48:30 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (! Schema::hasColumn('org_stocks', 'quantity_in_submitted_orders')) {
                $table->decimal('quantity_in_submitted_orders', 16, 3)->default(0);
            }

            if (! Schema::hasColumn('org_stocks', 'quantity_to_be_picked')) {
                $table->decimal('quantity_to_be_picked', 16, 3)->default(0);
            }
            if (! Schema::hasColumn('org_stocks', 'quantity_available')) {
                $table->decimal('quantity_available', 16, 3)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (Schema::hasColumn('org_stocks', 'quantity_in_submitted_orders')) {
                $table->dropColumn('quantity_in_submitted_orders');
            }
            if (Schema::hasColumn('org_stocks', 'quantity_to_be_picked')) {
                $table->dropColumn('quantity_to_be_picked');
            }

            if (Schema::hasColumn('org_stocks', 'quantity_to_be_picked')) {
                $table->dropColumn('quantity_to_be_picked');
            }
        });
    }
};
