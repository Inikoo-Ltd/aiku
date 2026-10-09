<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('packaging_families', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->string('code')->index()->collation('und_ns');
            $table->string('name')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('signature', 40)->nullable();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->unique(['group_id', 'signature']);
        });

        Schema::create('packaging_components', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->string('name')->nullable();
            $table->string('packaging_level')->index();
            $table->string('material_category')->index();
            $table->string('material')->nullable();
            $table->string('material_id_code')->nullable();
            $table->decimal('weight_g', 10, 3)->nullable();
            $table->boolean('weight_is_measured')->default(false);
            $table->decimal('recycled_content_pct', 5, 2)->nullable();
            $table->string('recycled_content_evidence')->nullable();
            $table->string('recyclability')->nullable();
            $table->string('separable')->nullable();
            $table->string('marks')->nullable();
            $table->string('national_marks')->nullable();
            $table->string('artwork_owner')->nullable();
            $table->text('notes')->nullable();
            $table->string('signature', 40)->nullable();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->unique(['group_id', 'signature']);
        });

        Schema::create('packaging_family_has_components', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('packaging_family_id')->index();
            $table->foreign('packaging_family_id')->references('id')->on('packaging_families')->cascadeOnDelete();
            $table->unsignedInteger('packaging_component_id')->index();
            $table->foreign('packaging_component_id')->references('id')->on('packaging_components');
            $table->decimal('quantity', 12, 6)->default(1);
            $table->decimal('quantity_per_unit', 12, 6)->default(1);
            $table->timestampsTz();
            $table->unique(['packaging_family_id', 'packaging_component_id']);
        });

        Schema::table('trade_units', function (Blueprint $table) {
            $table->unsignedInteger('packaging_family_id')->nullable()->index();
            $table->foreign('packaging_family_id')->references('id')->on('packaging_families')->nullOnDelete();
            $table->jsonb('compliance')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropForeign(['packaging_family_id']);
            $table->dropColumn(['packaging_family_id', 'compliance']);
        });
        Schema::dropIfExists('packaging_family_has_components');
        Schema::dropIfExists('packaging_components');
        Schema::dropIfExists('packaging_families');
    }
};
