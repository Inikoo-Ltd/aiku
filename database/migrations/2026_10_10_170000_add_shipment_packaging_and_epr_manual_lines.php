<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->boolean('is_shipment_packaging')->default(false)->index();
        });

        Schema::create('epr_manual_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->date('date_from');
            $table->date('date_to');
            $table->string('activity', 4);
            $table->string('packaging_type', 4);
            $table->string('packaging_class', 4);
            $table->string('material_category', 32);
            $table->string('from_nation', 16)->nullable();
            $table->string('to_nation', 16)->nullable();
            $table->decimal('kg', 12, 3);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epr_manual_lines');
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn('is_shipment_packaging');
        });
    }
};
