<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('production_drop_points', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::table('production_material_requests', function (Blueprint $table) {
            $table->string('source')->default('materials_check')->index();
        });

        Schema::table('production_material_request_items', function (Blueprint $table) {
            $table->unsignedInteger('raw_material_id')->nullable()->change();
            $table->decimal('needed_units', 16, 3)->nullable()->change();
            $table->decimal('missing_units', 16, 3)->nullable()->change();
            $table->decimal('box_units', 16, 3)->nullable()->change();
            $table->unsignedInteger('boxes')->nullable()->change();
            $table->decimal('skos_to_bring', 16, 3)->nullable()->change();
            $table->jsonb('job_orders')->nullable()->change();

            $table->unsignedSmallInteger('production_drop_point_id')->nullable()->index();
            $table->foreign('production_drop_point_id')->references('id')->on('production_drop_points')->nullOnDelete();
            $table->decimal('requested_quantity', 16, 3)->nullable();
            $table->string('requested_unit')->nullable();
            $table->boolean('is_urgent')->default(false)->index();
            $table->text('note')->nullable();
            $table->decimal('unbooked_skos', 16, 3)->default(0);
            $table->string('short_reason')->nullable();
            $table->dateTimeTz('short_at')->nullable();
        });

        $factory = DB::table('productions')->where('slug', 'awa')->first(['id', 'group_id', 'organisation_id']);
        if ($factory) {
            $now = now();
            foreach (['Storage room', 'Clean room', 'CORNY', 'Neil', 'Zack', 'Audry', 'Bath bomb area'] as $position => $name) {
                DB::table('production_drop_points')->insert([
                    'group_id'        => $factory->group_id,
                    'organisation_id' => $factory->organisation_id,
                    'production_id'   => $factory->id,
                    'name'            => $name,
                    'position'        => $position,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('production_material_request_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_drop_point_id');
            $table->dropColumn(['requested_quantity', 'requested_unit', 'is_urgent', 'note', 'unbooked_skos', 'short_reason', 'short_at']);
        });
        Schema::table('production_material_requests', function (Blueprint $table) {
            $table->dropColumn('source');
        });
        Schema::dropIfExists('production_drop_points');
    }
};
