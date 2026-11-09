<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Nov 2026 15:05:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->string('slug')->nullable()->collation('und_ns');
        });

        $taken = [];
        foreach (DB::table('competitors')->orderBy('id')->get(['id', 'name']) as $competitor) {
            $base = Str::limit(Str::slug($competitor->name) ?: 'competitor', 64, '');
            $slug = $base;
            for ($suffix = 1; isset($taken[$slug]); $suffix++) {
                $slug = "$base-$suffix";
            }
            $taken[$slug] = true;
            DB::table('competitors')->where('id', $competitor->id)->update(['slug' => $slug]);
        }

        Schema::table('competitors', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->collation('und_ns')->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
