<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Mar 2025 11:05:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_bill_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_transactions_type_spaces')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('recurring_bill_stats', function (Blueprint $table) {
            $table->dropColumn('number_transactions_type_spaces');
        });
    }
};
