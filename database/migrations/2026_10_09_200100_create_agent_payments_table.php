<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('agent_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('agent_id')->index();
            $table->foreign('agent_id')->references('id')->on('agents');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('stock_delivery_id')->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries');
            $table->date('date');
            $table->decimal('amount', 16);
            $table->unsignedSmallInteger('currency_id')->index();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('created_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_payments');
    }
};
