<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Jun 2025 14:09:58 British Summer Time, Sheffield, UK
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
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['webpage_id']);
            });
        }


        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('webpage_id')->references('id')->on('webpages')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Not implemented, as this is a one-way migration to allow for the deletion of webpages
    }
};
