<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 May 2024 12:03:45 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_recurring_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recurring_bill_id');
            $table->unsignedBigInteger('model_id');
            $table->string('model_type');
            $table->timestampsTz();
            $table->index(['model_id', 'model_type']);
            $table->unique(['recurring_bill_id', 'model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_recurring_bill');
    }
};
