<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            $table->text('dismiss_reason')->nullable();
            $table->dateTimeTz('dismissed_at')->nullable();
            $table->unsignedInteger('dismissed_by_user_id')->nullable();
            $table->foreign('dismissed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dismissed_by_user_id');
            $table->dropColumn(['dismiss_reason', 'dismissed_at']);
        });
    }
};
