<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Dec 2024 18:08:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->string('guid');
            $table->string('sort_order')->default(1);
            $table->longText('content')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->vector('embedding_768', 768)->nullable();
            $table->vector('embedding_1536', 1536)->nullable();
            $table->vector('embedding_2048', 2048)->nullable();
            $table->vector('embedding_3072', 3072)->nullable();
            $table->vector('embedding_1024', 1024)->nullable();
            $table->vector('embedding_4096', 4096)->nullable();
            $table->integer('section_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
