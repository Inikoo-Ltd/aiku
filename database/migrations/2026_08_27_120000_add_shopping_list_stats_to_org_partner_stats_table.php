<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_partner_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_shopping_list_items')->default(0);
            $table->unsignedInteger('number_open_shopping_list_items')->default(0)
                ->comment('state=open|dismiss_proposed, ie still to be picked by the partner');
        });
    }

    public function down(): void
    {
        Schema::table('org_partner_stats', function (Blueprint $table) {
            $table->dropColumn(['number_shopping_list_items', 'number_open_shopping_list_items']);
        });
    }
};
