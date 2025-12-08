<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Mar 2023 23:59:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_payments', function (Blueprint $table) {
            $table->unsignedInteger('payment_id');
            $table->foreign('payment_id')->references('id')->on('payments');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->decimal('amount', 12);
            $table->float('share')->default(1);
            $table->timestampsTz();
            $table->index(['model_type', 'model_id']);
            $table->unique(['payment_id', 'model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_payments');
    }
};
