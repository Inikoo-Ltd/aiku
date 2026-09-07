<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Aug 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('manufacture_pay_bands', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->string('code');
            $table->string('name')->nullable();
            $table->decimal('hourly_rate', 8, 2);
            $table->decimal('target_multiplier', 8, 4)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->dateTimeTz('effective_from');
            $table->dateTimeTz('effective_to')->nullable();
            $table->timestampsTz();
            $table->unique(['production_id', 'code', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacture_pay_bands');
    }
};
