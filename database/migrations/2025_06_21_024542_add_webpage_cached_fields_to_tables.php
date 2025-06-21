<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Jun 2025 17:29:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    protected array $tables = [
        'products',
        'product_categories',
        'collections'
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'webpage_id')) {
                    $table->unsignedInteger('webpage_id')->nullable()->index();
                    $table->foreign('webpage_id')->references('id')->on('webpages')->onDelete('cascade');
                }
                if (!Schema::hasColumn($tableName, 'url')) {
                    $table->string('url')->nullable()->index();
                }
                if (!Schema::hasColumn($tableName, 'images')) {
                    $table->jsonb('images')->default('{}');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'webpage_id')) {
                    $table->dropForeign(['webpage_id']);
                    $table->dropColumn('webpage_id');
                }
                if (Schema::hasColumn($tableName, 'url')) {
                    $table->dropColumn('url');
                }
                if (Schema::hasColumn($tableName, 'images')) {
                    $table->dropColumn('images');
                }
            });
        }
    }
};
