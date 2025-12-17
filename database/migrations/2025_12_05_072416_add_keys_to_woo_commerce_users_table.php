<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Dec 2025 10:44:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('woo_commerce_users', function (Blueprint $table) {
            $table->string('consumer_key')->nullable()->index();
            $table->string('consumer_secret')->nullable()->index();
            $table->string('store_url')->nullable()->index();
            $table->jsonb('error_response')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('woo_commerce_users', function (Blueprint $table) {
            $table->dropColumn('consumer_key');
            $table->dropColumn('consumer_secret');
            $table->dropColumn('store_url');
            $table->dropColumn('error_response');
        });
    }
};
