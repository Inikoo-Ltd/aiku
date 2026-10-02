<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('organisation_stock_history_sources', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('organisation_stock_history_id');
            $table->foreign('organisation_stock_history_id', 'oshs_history_fk')->references('id')->on('organisation_stock_histories')->cascadeOnDelete();
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->date('date');
            $table->string('source');
            $table->unsignedInteger('number_org_stocks')->default(0);
            $table->unsignedInteger('number_out_of_stock_org_stocks')->default(0);
            $table->decimal('estimated_lost_revenue_org_currency', 16)->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_stock_history_id', 'source'], 'oshs_history_source_unique');
            $table->index(['organisation_id', 'source', 'date'], 'oshs_organisation_source_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_stock_history_sources');
    }
};
