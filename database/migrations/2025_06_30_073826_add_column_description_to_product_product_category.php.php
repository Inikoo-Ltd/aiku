<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Jun 2025 08:58:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('description_title')->nullable();
            $table->text('description_extra')->nullable();
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('description_title')->nullable();
            $table->text('description_extra')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('description_title');
            $table->dropColumn('description_extra');
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('description_title');
            $table->dropColumn('description_extra');
        });
    }
};
