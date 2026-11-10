<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_search_logs', function (Blueprint $table) {
            $table->string('suggestion_kind', 32)->nullable();
            $table->text('suggestion_url')->nullable();
            $table->dateTimeTz('suggestion_clicked_at')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('website_search_logs', function (Blueprint $table) {
            $table->dropColumn(['suggestion_kind', 'suggestion_url', 'suggestion_clicked_at']);
        });
    }
};
