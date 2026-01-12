<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Jan 2026 16:38:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->decimal('tax_amount', 16)->default(0);
            $table->boolean('is_tax_only')->default(false);
            $table->decimal('amount_total', 16)->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
            $table->dropColumn('is_tax_only');
            $table->dropColumn('amount_total');
        });
    }
};
