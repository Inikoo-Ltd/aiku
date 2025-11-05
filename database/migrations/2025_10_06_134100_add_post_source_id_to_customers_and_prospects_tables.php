<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Oct 2025 14:14:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'post_source_id')) {
                $table->string('post_source_id')->nullable()->index();
            }
        });

        Schema::table('prospects', function (Blueprint $table) {
            if (!Schema::hasColumn('prospects', 'post_source_id')) {
                $table->string('post_source_id')->nullable()->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'post_source_id')) {
                // Drop index first if exists then column
                try {
                    $table->dropIndex('customers_post_source_id_index');
                } catch (\Throwable) {
                    // ignore if the index does not exist
                }
                $table->dropColumn('post_source_id');
            }
        });

        Schema::table('prospects', function (Blueprint $table) {
            if (Schema::hasColumn('prospects', 'post_source_id')) {
                try {
                    $table->dropIndex('prospects_post_source_id_index');
                } catch (\Throwable) {
                    // ignore if the index does not exist
                }
                $table->dropColumn('post_source_id');
            }
        });
    }
};
