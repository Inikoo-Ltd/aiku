<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Sept 2025 19:39:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('is_name_reviewed')->nullable();
            $table->boolean('is_description_title_reviewed')->nullable();
            $table->boolean('is_description_reviewed')->nullable();
            $table->boolean('is_description_extra_reviewed')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('is_name_reviewed');
            $table->dropColumn('is_description_title_reviewed');
            $table->dropColumn('is_description_reviewed');
            $table->dropColumn('is_description_extra_reviewed');
        });
    }
};
