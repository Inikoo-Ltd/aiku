<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Nov 2024 10:31:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasOrderingStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasOrderingStats;

    public function up(): void
    {
        Schema::create('invoice_category_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('invoice_category_id');
            $table->foreign('invoice_category_id')->references('id')->on('invoice_categories')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->invoicesStatsFields($table);
            $table = $this->invoicedCustomersStatsFields($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_category_stats');
    }
};
