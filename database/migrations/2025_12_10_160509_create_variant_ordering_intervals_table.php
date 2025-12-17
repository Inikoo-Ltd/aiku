<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 00:40:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasDateIntervalsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasDateIntervalsStats;
    public function up(): void
    {
        Schema::create('variant_sales_ordering_intervals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('variant_id')->index();
            $table->foreign('variant_id')->references('id')->on('variants')->onDelete('cascade')->onUpdate('cascade');
            $table = $this->unsignedIntegerDateIntervals($table, [
                'invoices',
                'refunds',
                'orders',
                'delivery_notes',
                'customers_invoiced'
            ]);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('variant_sales_ordering_intervals');
    }
};
