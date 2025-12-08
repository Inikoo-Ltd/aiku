<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Feb 2025 11:18:17 Central Indonesia Time, Kuala Lumpur, Malaysia
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
            $table->dropForeign(['order_id']);
            $table->renameColumn('order_id', 'model_id');
            $table->string('model_type')->after('model_id');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_user_has_fulfilments', function (Blueprint $table) {
            $table->renameColumn('model_id', 'order_id');
            $table->dropColumn('model_type');
            $table->foreign('order_id')->on('orders')->references('id');
        });
    }
};
