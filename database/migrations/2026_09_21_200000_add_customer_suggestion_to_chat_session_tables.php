<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const array TABLES = ['chat_sessions', 'meta_chat_sessions'];

    /**
     * Who a guest probably is, kept apart from who they are: a typed email or a quoted order
     * number is a claim, and the link to the customer is only made when an agent confirms it.
     */
    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('suggested_customer_id')->nullable()->index();
                $table->string('suggestion_basis')->nullable();
                $table->string('suggestion_hint')->nullable();
                $table->dateTimeTz('suggestion_rejected_at')->nullable();
                $table->dateTimeTz('customer_asked_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['suggested_customer_id', 'suggestion_basis', 'suggestion_hint', 'suggestion_rejected_at', 'customer_asked_at']);
            });
        }
    }
};
