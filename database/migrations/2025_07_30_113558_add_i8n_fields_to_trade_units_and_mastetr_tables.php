<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Aug 2025 08:53:34 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {

        Schema::table('trade_units', function (Blueprint $table) {
            $table->jsonb('name_i8n')->nullable();
            $table->jsonb('description_i8n')->nullable();
            $table->jsonb('description_title_i8n')->nullable();
            $table->jsonb('description_extra_i8n')->nullable();
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->jsonb('name_i8n')->nullable();
            $table->jsonb('description_i8n')->nullable();
            $table->jsonb('description_title_i8n')->nullable();
            $table->jsonb('description_extra_i8n')->nullable();
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->jsonb('name_i8n')->nullable();
            $table->jsonb('description_i8n')->nullable();
            $table->jsonb('description_title_i8n')->nullable();
            $table->jsonb('description_extra_i8n')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropColumn([
                'name_i8n',
                'description_i8n',
                'description_title_i8n',
                'description_extra_i8n'
            ]);
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn([
                'name_i8n',
                'description_i8n',
                'description_title_i8n',
                'description_extra_i8n'
            ]);
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'name_i8n',
                'description_i8n',
                'description_title_i8n',
                'description_extra_i8n'
            ]);
        });
    }
};
