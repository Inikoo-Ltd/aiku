<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 20:53:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stock_value_findings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->unsignedInteger('org_stock_id');
            $table->string('rule', 32);
            $table->string('owner', 16)->index();
            $table->string('state', 16)->default('open')->index();
            $table->decimal('excess_amount', 16, 2)->default(0);
            $table->jsonb('facts')->default('{}');
            $table->text('explanation')->nullable();
            $table->unsignedBigInteger('cause_org_stock_movement_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedBigInteger('staff_task_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->string('ticket_key', 128)->nullable()->index();
            $table->dateTimeTz('first_seen_at');
            $table->dateTimeTz('last_seen_at');
            $table->dateTimeTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->unique(['org_stock_id', 'rule']);
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_value_findings');
    }
};
