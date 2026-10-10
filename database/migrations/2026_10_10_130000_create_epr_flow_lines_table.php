<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('epr_flow_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->date('date');
            $table->string('activity', 24);
            $table->string('source_type', 24);
            $table->unsignedBigInteger('source_id');
            $table->unsignedSmallInteger('counterparty_country_id')->nullable();
            $table->foreign('counterparty_country_id')->references('id')->on('countries');
            $table->unsignedInteger('org_stock_id')->nullable()->index();
            $table->unsignedInteger('trade_unit_id')->nullable();
            $table->unsignedInteger('packaging_family_id')->nullable();
            $table->decimal('sko_quantity', 16, 4);
            $table->decimal('quantity', 16, 4);
            $table->string('shop_type', 16)->nullable();
            $table->unsignedBigInteger('platform_id')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->index(['organisation_id', 'date', 'activity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epr_flow_lines');
    }
};
