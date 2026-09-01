<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('artefact_families', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code', 64)->index()->collation('und_ns');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('number_artefacts')->default(0);
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::table('artefacts', function (Blueprint $table) {
            $table->unsignedInteger('artefact_family_id')->nullable()->index();
            $table->foreign('artefact_family_id')->references('id')->on('artefact_families');
        });

        $categories = DB::table('artefacts')
            ->select('production_id', 'group_id', 'organisation_id', 'category', DB::raw('count(*) as number_artefacts'))
            ->whereNotNull('category')
            ->whereNull('deleted_at')
            ->groupBy('production_id', 'group_id', 'organisation_id', 'category')
            ->get();

        foreach ($categories as $category) {
            $code = Str::slug($category->category);
            $slug = DB::table('artefact_families')->where('slug', $code)->exists() ? $code.'-'.$category->production_id : $code;
            $familyId = DB::table('artefact_families')->insertGetId([
                'group_id'         => $category->group_id,
                'organisation_id'  => $category->organisation_id,
                'production_id'    => $category->production_id,
                'slug'             => $slug,
                'code'             => $code,
                'name'             => $category->category,
                'number_artefacts' => $category->number_artefacts,
                'data'             => '{}',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            DB::table('artefacts')
                ->where('production_id', $category->production_id)
                ->where('category', $category->category)
                ->update(['artefact_family_id' => $familyId]);
        }

        Schema::table('artefacts', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('artefacts', function (Blueprint $table) {
            $table->string('category')->nullable()->index();
            $table->dropForeign(['artefact_family_id']);
            $table->dropColumn('artefact_family_id');
        });
        Schema::dropIfExists('artefact_families');
    }
};
