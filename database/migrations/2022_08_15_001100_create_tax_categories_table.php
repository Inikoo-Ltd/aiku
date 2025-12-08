<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 15:11:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_categories', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('type')->index();
            $table->string('type_name')->index();
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('label')->collation('und_ci');
            $table->string('name')->collation('und_ci');
            $table->boolean('status')->default(true)->index();
            $table->decimal('rate', 7, 4)->default(0);
            $table->unsignedSmallInteger('country_id')->nullable()->index();
            $table->foreign('country_id')->references('id')->on('countries');
            $table->jsonb('data');
            $table->string('source_id')->nullable()->index();
            $table->timestampsTz();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_categories');
    }
};
