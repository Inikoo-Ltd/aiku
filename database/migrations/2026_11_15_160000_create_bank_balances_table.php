<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bank_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedBigInteger('bank_connection_id');
            $table->foreign('bank_connection_id')->references('id')->on('bank_connections');
            $table->unsignedSmallInteger('currency_id')->index();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->date('date');
            $table->decimal('amount', 18, 2);
            $table->decimal('reserved_amount', 18, 2)->default(0);
            $table->timestampTz('taken_at');
            $table->timestampsTz();
            $table->unique(['bank_connection_id', 'currency_id', 'date']);
            $table->index(['organisation_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_balances');
    }
};
