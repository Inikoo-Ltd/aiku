<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * What the customer wanted, as a column rather than inside the summary json, because it is
     * there to be counted: per shop, per month, per customer. When it was last summarised sits
     * beside it so a conversation that carried on afterwards can be found and done again.
     */
    public function up(): void
    {
        foreach (['chat_sessions', 'meta_chat_sessions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('topic')->nullable()->index();
                $table->dateTimeTz('summarised_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['chat_sessions', 'meta_chat_sessions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['topic', 'summarised_at']);
            });
        }
    }
};
