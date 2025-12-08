<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Feb 2025 13:20:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_user_has_fulfilments', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_client_id')->nullable();
            $table->foreign('customer_client_id')->references('id')->on('customer_clients');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_user_has_fulfilments', function (Blueprint $table) {
            $table->dropForeign(['customer_client_id']);
            $table->dropColumn(['customer_client_id']);
        });
    }
};
