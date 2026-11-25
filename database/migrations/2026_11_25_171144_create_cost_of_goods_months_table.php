<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 01:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('cost_of_goods_months', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->char('month', 7);
            $table->decimal('org_amount', 18, 4)->default(0);
            $table->decimal('grp_amount_external', 18, 4)->default(0);
            $table->timestampsTz();
            $table->unique(['organisation_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_of_goods_months');
    }
};
