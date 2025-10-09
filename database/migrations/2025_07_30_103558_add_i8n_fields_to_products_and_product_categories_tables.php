<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 31 Jul 2025 13:09:44 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->jsonb('name_i8n')->nullable();
            $table->jsonb('description_i8n')->nullable();
            $table->jsonb('description_title_i8n')->nullable();
            $table->jsonb('description_extra_i8n')->nullable();
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->jsonb('name_i8n')->nullable();
            $table->jsonb('description_i8n')->nullable();
            $table->jsonb('description_title_i8n')->nullable();
            $table->jsonb('description_extra_i8n')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'name_i8n',
                'description_i8n',
                'description_title_i8n',
                'description_extra_i8n'
            ]);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'name_i8n',
                'description_i8n',
                'description_title_i8n',
                'description_extra_i8n'
            ]);
        });
    }
};
